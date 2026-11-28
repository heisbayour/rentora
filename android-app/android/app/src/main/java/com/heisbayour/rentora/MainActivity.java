package com.heisbayour.rentora;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
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
import java.nio.charset.StandardCharsets;
import java.util.Map;
import java.util.function.Consumer;
import org.json.JSONObject;

/**
 * Rentora app: one Capacitor WebView showing the live tenant portal, full
 * screen, with no browser UI. Everything native lives here and in the
 * classes next to it (see android-app/README.md).
 */
public class MainActivity extends BridgeActivity {

    static final String HOST = "rentora-housing.infinityfreeapp.com";
    static final String ORIGIN = "https://" + HOST;
    static final String START_URL = ORIGIN + "/portal";
    static final int BRAND_BLUE = 0xFF1A73E8;

    private ActivityResultLauncher<String[]> permissionLauncher;
    private ActivityResultLauncher<Intent> activityLauncher;
    private Consumer<Boolean> pendingPermission;
    private Consumer<ActivityResult> pendingActivity;

    private SwipeRefreshLayout swipe;
    private FileChooser fileChooser;
    private NativeBridge nativeBridge;

    private volatile String currentUrl = START_URL;
    private String failedUrl;
    private boolean offlineShown;
    private boolean loadingOfflinePage;

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
    }

    @Override
    public void onPause() {
        super.onPause();
        // Keep the login: write session cookies to disk now, not "eventually".
        CookieManager.getInstance().flush();
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
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, length) ->
            nativeBridge.download(webView, url, URLUtil.guessFileName(url, contentDisposition, mimeType), mimeType)
        );
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
                    webView.goBack();
                } else {
                    webView.loadUrl(START_URL);
                }
            }
        });
    }

    /** Home pages: the portal, the console dashboard, the login page and the site root. */
    static boolean isHomePage(String url) {
        if (url == null) {
            return true;
        }
        Uri uri = Uri.parse(url);
        if (!HOST.equalsIgnoreCase(uri.getHost())) {
            return false;
        }
        String path = uri.getPath() == null ? "" : uri.getPath().replaceAll("/+$", "");
        return path.isEmpty() || path.equals("/portal") || path.equals("/login") || path.equals("/app");
    }

    static boolean isOwnSite(String url) {
        return url != null && HOST.equalsIgnoreCase(Uri.parse(url).getHost());
    }

    // ------------------------------------------------------- page lifecycle

    void onPageStarted(String url) {
        if (!loadingOfflinePage) {
            offlineShown = false;
            if (url != null && url.startsWith("http")) {
                currentUrl = url;
            }
        }
    }

    void onPageFinished() {
        loadingOfflinePage = false;
        swipe.setRefreshing(false);
    }

    String currentUrl() {
        return currentUrl;
    }

    private void reload() {
        WebView webView = getBridge().getWebView();
        if (offlineShown && failedUrl != null) {
            webView.loadUrl(failedUrl);
        } else {
            webView.reload();
        }
    }

    /** Shown instead of the WebView's own error page when the site cannot be reached. */
    void showOffline(String url) {
        failedUrl = isOwnSite(url) ? url : START_URL;
        offlineShown = true;
        loadingOfflinePage = true;
        swipe.setRefreshing(false);
        String html = readAsset("public/offline.html").replace("'__RETRY_URL__'", JSONObject.quote(failedUrl));
        getBridge().getWebView().loadDataWithBaseURL(ORIGIN + "/", html, "text/html", "UTF-8", failedUrl);
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
            return "<html><body style='font-family:sans-serif;padding:24px'><h1>No connection</h1><p>Check your internet connection.</p>"
                + "<button onclick=\"location.replace('" + START_URL + "')\">Retry</button></body></html>";
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
