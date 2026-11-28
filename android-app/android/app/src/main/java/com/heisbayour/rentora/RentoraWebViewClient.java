package com.heisbayour.rentora;

import android.graphics.Bitmap;
import android.net.Uri;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
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
 * - A page that cannot load because there is no connection shows the
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
                return super.shouldOverrideUrlLoading(view, request);
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
    public void onPageStarted(WebView view, String url, Bitmap favicon) {
        super.onPageStarted(view, url, favicon);
        activity.onPageStarted(url);
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        super.onPageFinished(view, url);
        activity.onPageFinished();
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        super.onReceivedError(view, request, error);
        if (request.isForMainFrame() && error.getErrorCode() != WebViewClient.ERROR_UNSUPPORTED_SCHEME) {
            activity.showOffline(request.getUrl().toString());
        }
    }
}
