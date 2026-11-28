package com.heisbayour.rentora;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.webkit.CookieManager;
import android.webkit.URLUtil;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.widget.Toast;
import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResult;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.getcapacitor.BridgeActivity;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.function.Consumer;
import org.json.JSONObject;
import org.json.JSONTokener;

/**
 * Rentora app: one Capacitor WebView showing the live site, full screen,
 * with no browser UI. Everything native lives here and in the classes next
 * to it (see android-app/README.md).
 */
public class MainActivity extends BridgeActivity {

    static final String HOST = "rentora-housing.infinityfreeapp.com";
    static final String ORIGIN = "https://" + HOST;
    /** Sends each role home: staff to /app, tenants to /portal, guests to /login. */
    static final String START_URL = ORIGIN + "/start";
    static final int BRAND_BLUE = 0xFF1A73E8;

    /** A main page that has drawn nothing after this long counts as failed. */
    private static final long LOAD_TIMEOUT_MS = 30_000;
    /** How long the "does the site answer" check may take. */
    private static final int PROBE_TIMEOUT_MS = 8_000;
    private static final int AUTO_RETRY_ATTEMPTS = 3;
    private static final long AUTO_RETRY_DELAY_MS = 1_500;

    /** Runs after every page load: is it the offline screen, or did it come back empty? */
    private static final String PAGE_CHECK_JS =
        "(function(){var b=document.body;return JSON.stringify({" +
        "offline:!!window.__rentoraOffline," +
        "blank:!b||(b.children.length===0&&(b.innerText||'').trim()==='')});})()";

    private ActivityResultLauncher<String[]> permissionLauncher;
    private ActivityResultLauncher<Intent> activityLauncher;
    private Consumer<Boolean> pendingPermission;
    private Consumer<ActivityResult> pendingActivity;

    private SwipeRefreshLayout swipe;
    private FileChooser fileChooser;
    private NativeBridge nativeBridge;

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final ExecutorService probeExecutor = Executors.newSingleThreadExecutor();
    private final Runnable loadTimeout = this::onLoadTimeout;
    private final Runnable autoRetry = () -> retry(false);
    private ConnectivityManager.NetworkCallback networkCallback;

    private volatile String currentUrl = START_URL;
    private String loadingUrl = START_URL;
    private String failedUrl;
    /** The offline screen is showing (or about to). Cleared only when a real page has loaded. */
    private boolean offlineShown;
    /** The offline screen was requested but has not reported itself yet. */
    private boolean offlinePending;
    /** A Retry is checking the connection or loading the page. */
    private boolean retrying;
    private int autoRetriesLeft;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        // Result launchers must exist before the activity starts.
        permissionLauncher = registerForActivityResult(new ActivityResultContracts.RequestMultiplePermissions(), this::onPermissionResult);
        activityLauncher = registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), this::onActivityResultReceived);

        super.onCreate(savedInstanceState);

        setUpSystemBars();
        WebView webView = getBridge().getWebView();
        fileChooser = new FileChooser(this);
        nativeBridge = new NativeBridge(this);
        setUpWebView(webView);
        setUpPullToRefresh(webView);
        setUpBackButton();
        setUpNetworkCallback();
        // The bridge has already started loading START_URL.
        armLoadTimeout(START_URL);
    }

    @Override
    public void onResume() {
        super.onResume();
        if (offlineShown) {
            scheduleAutoRetry();
        }
    }

    @Override
    public void onPause() {
        super.onPause();
        // Keep the login: write session cookies to disk now, not "eventually".
        CookieManager.getInstance().flush();
    }

    @Override
    public void onDestroy() {
        mainHandler.removeCallbacksAndMessages(null);
        if (networkCallback != null) {
            try {
                connectivity().unregisterNetworkCallback(networkCallback);
            } catch (Exception ignored) {
                // Already unregistered.
            }
        }
        probeExecutor.shutdownNow();
        super.onDestroy();
    }

    // ---------------------------------------------------------------- setup

    /** Status bar #1A73E8 with white icons on every Android version, including edge-to-edge (15+). */
    private void setUpSystemBars() {
        Window window = getWindow();
        WindowCompat.setDecorFitsSystemWindows(window, false);
        window.setStatusBarColor(Color.TRANSPARENT);
        window.setNavigationBarColor(Color.TRANSPARENT);
        window.getDecorView().setBackgroundColor(BRAND_BLUE);
        WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(window, window.getDecorView());
        controller.setAppearanceLightStatusBars(false);
        controller.setAppearanceLightNavigationBars(false);

        // The page never draws under the bars: pad the content by the bar and
        // keyboard sizes so the blue window shows through above and below.
        View content = findViewById(android.R.id.content);
        ViewCompat.setOnApplyWindowInsetsListener(content, (v, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout());
            Insets ime = insets.getInsets(WindowInsetsCompat.Type.ime());
            v.setPadding(bars.left, bars.top, bars.right, Math.max(bars.bottom, ime.bottom));
            return WindowInsetsCompat.CONSUMED;
        });
    }

    private void setUpWebView(WebView webView) {
        WebSettings settings = webView.getSettings();
        // InfinityFree's browser check needs JavaScript and cookies.
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setSupportMultipleWindows(false);
        settings.setMediaPlaybackRequiresUserGesture(true);

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(webView, true);

        getBridge().setWebViewClient(new RentoraWebViewClient(getBridge(), this));
        webView.setWebChromeClient(new RentoraChromeClient(getBridge(), fileChooser));
        webView.addJavascriptInterface(nativeBridge, NativeBridge.NAME);
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, length) -> {
            // A link that turned into a download is not a page that failed to load.
            cancelLoadTimeout();
            nativeBridge.download(webView, url, URLUtil.guessFileName(url, contentDisposition, mimeType), mimeType);
        });
    }

    private void setUpPullToRefresh(WebView webView) {
        ViewGroup parent = (ViewGroup) webView.getParent();
        int index = parent.indexOfChild(webView);
        ViewGroup.LayoutParams params = webView.getLayoutParams();
        parent.removeView(webView);

        swipe = new SwipeRefreshLayout(this);
        swipe.setColorSchemeColors(BRAND_BLUE);
        swipe.addView(webView, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        parent.addView(swipe, index, params);

        // Only when the page is scrolled to the top.
        swipe.setOnChildScrollUpCallback((p, child) -> webView.getScrollY() > 0 || webView.canScrollVertically(-1));
        swipe.setOnRefreshListener(this::reload);
    }

    /** Back goes back in page history; the app closes only from a home page. */
    private void setUpBackButton() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                WebView webView = getBridge().getWebView();
                String url = offlineShown ? failedUrl : webView.getUrl();
                if (isHomePage(url)) {
                    finish();
                } else if (webView.canGoBack()) {
                    armLoadTimeout(null);
                    webView.goBack();
                } else {
                    loadPage(START_URL);
                }
            }
        });
    }

    /** When mobile data or Wi-Fi comes back while the offline screen shows, reconnect by itself. */
    private void setUpNetworkCallback() {
        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                mainHandler.post(MainActivity.this::onNetworkBack);
            }

            @Override
            public void onCapabilitiesChanged(Network network, NetworkCapabilities caps) {
                if (caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)) {
                    mainHandler.post(MainActivity.this::onNetworkBack);
                }
            }
        };
        try {
            connectivity().registerDefaultNetworkCallback(networkCallback);
        } catch (Exception e) {
            networkCallback = null;
        }
    }

    /** Home pages: the start route, the portal, the console dashboard, the login page and the site root. */
    static boolean isHomePage(String url) {
        if (url == null) {
            return true;
        }
        Uri uri = Uri.parse(url);
        if (!HOST.equalsIgnoreCase(uri.getHost())) {
            return false;
        }
        String path = uri.getPath() == null ? "" : uri.getPath().replaceAll("/+$", "");
        return path.isEmpty() || path.equals("/start") || path.equals("/portal") || path.equals("/login") || path.equals("/app");
    }

    static boolean isOwnSite(String url) {
        return url != null && HOST.equalsIgnoreCase(Uri.parse(url).getHost());
    }

    // ------------------------------------------------------- page lifecycle

    /** Every main-page load the app starts goes through here, so a hang is caught. */
    void loadPage(String url) {
        armLoadTimeout(url);
        getBridge().getWebView().loadUrl(url);
    }

    /** A link in the page is about to load inside the app. */
    void onNavigationStarting(String url) {
        armLoadTimeout(url);
    }

    void onPageStarted(String url) {
        if (url != null && url.startsWith("http")) {
            loadingUrl = url;
            if (!offlineShown) {
                currentUrl = url;
            }
        }
    }

    /** The page has started to draw: it is not hanging. */
    void onPageVisible() {
        cancelLoadTimeout();
    }

    void onPageFinished(WebView view, String url) {
        cancelLoadTimeout();
        swipe.setRefreshing(false);
        view.evaluateJavascript(PAGE_CHECK_JS, result -> onPageChecked(view, url, result));
    }

    /** A main page failed to load (no connection, DNS, timeout, TLS). */
    void onMainFrameError(String url) {
        showOffline(url);
    }

    String currentUrl() {
        return currentUrl;
    }

    private void onPageChecked(WebView view, String url, String result) {
        boolean offline = false;
        boolean blank = false;
        try {
            // evaluateJavascript hands back the returned string as a JSON string literal.
            Object value = new JSONTokener(result).nextValue();
            if (value instanceof String) {
                JSONObject state = new JSONObject((String) value);
                offline = state.optBoolean("offline");
                blank = state.optBoolean("blank");
            }
        } catch (Exception e) {
            // No answer (no script context): treat it as a normal page.
        }

        if (offline) {
            offlinePending = false;
            retrying = false;
            return;
        }
        if (offlinePending) {
            // The end of the load that failed, arriving after the offline screen was requested.
            return;
        }
        if (blank || "about:blank".equals(view.getUrl())) {
            showOffline(url != null && url.startsWith("http") ? url : loadingUrl);
            return;
        }
        // A real page has loaded.
        offlineShown = false;
        retrying = false;
        autoRetriesLeft = 0;
        mainHandler.removeCallbacks(autoRetry);
        String shown = view.getUrl();
        if (shown != null && shown.startsWith("http")) {
            currentUrl = shown;
        }
    }

    private void armLoadTimeout(String url) {
        if (url != null && url.startsWith("http")) {
            loadingUrl = url;
        }
        mainHandler.removeCallbacks(loadTimeout);
        mainHandler.postDelayed(loadTimeout, LOAD_TIMEOUT_MS);
    }

    private void cancelLoadTimeout() {
        mainHandler.removeCallbacks(loadTimeout);
    }

    private void onLoadTimeout() {
        getBridge().getWebView().stopLoading();
        showOffline(loadingUrl);
    }

    /** Pull to refresh: on the offline screen it does the same as Retry. */
    private void reload() {
        if (offlineShown) {
            swipe.setRefreshing(false);
            retry(true);
        } else {
            WebView webView = getBridge().getWebView();
            armLoadTimeout(webView.getUrl());
            webView.reload();
        }
    }

    /** Shown instead of the WebView's error page or a blank page whenever a main page cannot load. */
    void showOffline(String url) {
        cancelLoadTimeout();
        mainHandler.removeCallbacks(autoRetry);
        if (isOwnSite(url)) {
            failedUrl = url;
        } else if (failedUrl == null || !offlineShown) {
            failedUrl = isOwnSite(currentUrl) ? currentUrl : START_URL;
        }
        offlineShown = true;
        offlinePending = true;
        retrying = false;
        swipe.setRefreshing(false);
        String html = readAsset("public/offline.html").replace("'__RETRY_URL__'", JSONObject.quote(failedUrl));
        getBridge().getWebView().loadDataWithBaseURL(ORIGIN + "/", html, "text/html", "UTF-8", failedUrl);
    }

    /** The Retry button on the offline screen (through NativeBridge). */
    void retryFromPage() {
        if (offlineShown) {
            retry(true);
        }
    }

    /**
     * Checks that the site really answers before reloading, so Retry never
     * starts a load that is bound to fail. Without a connection the offline
     * screen stays and says so.
     */
    private void retry(boolean userInitiated) {
        if (!offlineShown || retrying) {
            return;
        }
        if (userInitiated) {
            autoRetriesLeft = 0;
            mainHandler.removeCallbacks(autoRetry);
        }
        if (!hasNetwork()) {
            if (userInitiated) {
                tellOfflinePage("rentoraStillOffline");
            }
            return;
        }
        retrying = true;
        tellOfflinePage("rentoraConnecting");
        String target = failedUrl != null ? failedUrl : START_URL;
        try {
            probeExecutor.execute(() -> {
                boolean reachable = siteAnswers();
                mainHandler.post(() -> onProbeResult(reachable, target, userInitiated));
            });
        } catch (Exception e) {
            retrying = false;
        }
    }

    private void onProbeResult(boolean reachable, String target, boolean userInitiated) {
        if (!offlineShown) {
            retrying = false;
            return;
        }
        if (reachable) {
            // Stays "retrying" until the page loads or the offline screen comes back.
            offlinePending = false;
            loadPage(target);
            return;
        }
        retrying = false;
        tellOfflinePage("rentoraStillOffline");
        if (!userInitiated && autoRetriesLeft > 0) {
            autoRetriesLeft--;
            mainHandler.postDelayed(autoRetry, AUTO_RETRY_DELAY_MS * 2);
        }
    }

    /** Any HTTP answer from the site (even an error page) means the connection works. */
    private boolean siteAnswers() {
        HttpURLConnection connection = null;
        try {
            connection = (HttpURLConnection) new URL(ORIGIN + "/favicon.ico").openConnection();
            connection.setRequestMethod("HEAD");
            connection.setInstanceFollowRedirects(false);
            connection.setUseCaches(false);
            connection.setConnectTimeout(PROBE_TIMEOUT_MS);
            connection.setReadTimeout(PROBE_TIMEOUT_MS);
            return connection.getResponseCode() > 0;
        } catch (Exception e) {
            return false;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    private void tellOfflinePage(String function) {
        getBridge().getWebView().evaluateJavascript("window." + function + "&&window." + function + "()", null);
    }

    private ConnectivityManager connectivity() {
        return (ConnectivityManager) getSystemService(CONNECTIVITY_SERVICE);
    }

    private boolean hasNetwork() {
        try {
            ConnectivityManager manager = connectivity();
            Network network = manager.getActiveNetwork();
            NetworkCapabilities caps = network == null ? null : manager.getNetworkCapabilities(network);
            return caps != null && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        } catch (Exception e) {
            // Cannot tell: let the reachability check decide.
            return true;
        }
    }

    private void onNetworkBack() {
        if (offlineShown && !retrying) {
            scheduleAutoRetry();
        }
    }

    private void scheduleAutoRetry() {
        autoRetriesLeft = AUTO_RETRY_ATTEMPTS;
        mainHandler.removeCallbacks(autoRetry);
        mainHandler.postDelayed(autoRetry, AUTO_RETRY_DELAY_MS);
    }

    private String readAsset(String path) {
        try (InputStream in = getAssets().open(path); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] buffer = new byte[8192];
            int read;
            while ((read = in.read(buffer)) != -1) {
                out.write(buffer, 0, read);
            }
            return out.toString(StandardCharsets.UTF_8.name());
        } catch (Exception e) {
            return "<html><body style='font-family:sans-serif;padding:24px'><script>window.__rentoraOffline=true;</script>"
                + "<h1>No connection</h1><p>Check your internet connection.</p>"
                + "<button onclick=\"" + NativeBridge.NAME + ".retry()\">Retry</button></body></html>";
        }
    }

    // --------------------------------------------------- intents and results

    /** tel:, mailto:, wa.me and other sites open in the matching app. */
    void openExternal(Uri uri) {
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, uri);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(intent);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, R.string.no_app_for_link, Toast.LENGTH_SHORT).show();
        }
    }

    /** Chrome-style intent:// links (some share buttons use them). */
    void openIntentUri(String url) {
        try {
            Intent intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
            intent.addCategory(Intent.CATEGORY_BROWSABLE);
            intent.setComponent(null);
            intent.setSelector(null);
            startActivity(intent);
        } catch (Exception e) {
            try {
                String fallback = Intent.parseUri(url, Intent.URI_INTENT_SCHEME).getStringExtra("browser_fallback_url");
                if (fallback != null) {
                    openExternal(Uri.parse(fallback));
                    return;
                }
            } catch (Exception ignored) {
                // Fall through to the message below.
            }
            Toast.makeText(this, R.string.no_app_for_link, Toast.LENGTH_SHORT).show();
        }
    }

    void requestPermissions(String[] permissions, Consumer<Boolean> callback) {
        pendingPermission = callback;
        permissionLauncher.launch(permissions);
    }

    void launchForResult(Intent intent, Consumer<ActivityResult> callback) {
        pendingActivity = callback;
        try {
            activityLauncher.launch(intent);
        } catch (ActivityNotFoundException e) {
            pendingActivity = null;
            callback.accept(new ActivityResult(RESULT_CANCELED, null));
        }
    }

    private void onPermissionResult(Map<String, Boolean> result) {
        boolean granted = !result.isEmpty();
        for (Boolean value : result.values()) {
            granted &= Boolean.TRUE.equals(value);
        }
        Consumer<Boolean> callback = pendingPermission;
        pendingPermission = null;
        if (callback != null) {
            callback.accept(granted);
        }
    }

    private void onActivityResultReceived(ActivityResult result) {
        Consumer<ActivityResult> callback = pendingActivity;
        pendingActivity = null;
        if (callback != null) {
            callback.accept(result);
        }
    }
}
