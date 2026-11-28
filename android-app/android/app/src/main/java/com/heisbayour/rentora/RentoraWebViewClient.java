package com.heisbayour.rentora;

import android.graphics.Bitmap;
import android.net.Uri;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;
import java.util.Locale;

/**
 * Link routing and the offline screen.
 *
 * - The live site and Paystack (capacitor.config.json allowNavigation) stay in
 *   the app; Capacitor opens every other http(s) site in the browser or the
 *   matching app (wa.me opens WhatsApp).
 * - tel:, mailto:, sms:, whatsapp: and intent:// links open their app.
 * - Pages of the site load directly in the WebView, not through Capacitor's
 *   proxy: the proxy turns a connection that drops mid-page into an empty
 *   200 response, which showed as a white page with no error.
 * - A page that cannot load (no connection, timeout, blank result) shows the
 *   offline screen. HTTP errors (403, 404, 500) are real pages and show as is.
 */
public class RentoraWebViewClient extends BridgeWebViewClient {

    private final MainActivity activity;

    public RentoraWebViewClient(Bridge bridge, MainActivity activity) {
        super(bridge);
        this.activity = activity;
    }

    @Override
    public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
        Uri url = request.getUrl();
        String scheme = url.getScheme() == null ? "" : url.getScheme().toLowerCase(Locale.US);
        switch (scheme) {
            case "http":
            case "https":
                boolean handledOutside = super.shouldOverrideUrlLoading(view, request);
                if (!handledOutside && request.isForMainFrame()) {
                    activity.onNavigationStarting(url.toString());
                }
                return handledOutside;
            case "intent":
                activity.openIntentUri(url.toString());
                return true;
            case "data":
            case "blob":
            case "about":
            case "javascript":
                return false;
            default:
                activity.openExternal(url);
                return true;
        }
    }

    @Override
    public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
        if (request.isForMainFrame() && MainActivity.isOwnSite(request.getUrl().toString())) {
            // Let the WebView load the page itself, so failures reach onReceivedError.
            return null;
        }
        return super.shouldInterceptRequest(view, request);
    }

    @Override
    public void onPageStarted(WebView view, String url, Bitmap favicon) {
        super.onPageStarted(view, url, favicon);
        activity.onPageStarted(url);
    }

    @Override
    public void onPageCommitVisible(WebView view, String url) {
        super.onPageCommitVisible(view, url);
        activity.onPageVisible();
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        super.onPageFinished(view, url);
        activity.onPageFinished(view, url);
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        super.onReceivedError(view, request, error);
        if (!request.isForMainFrame() || error.getErrorCode() == WebViewClient.ERROR_UNSUPPORTED_SCHEME) {
            return;
        }
        CharSequence description = error.getDescription();
        if (description != null && description.toString().contains("ERR_ABORTED")) {
            // Cancelled by a newer navigation or a download, not a failure.
            return;
        }
        activity.onMainFrameError(request.getUrl().toString());
    }
}
