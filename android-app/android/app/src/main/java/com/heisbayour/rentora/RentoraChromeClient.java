package com.heisbayour.rentora;

import android.net.Uri;
import android.webkit.ValueCallback;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebChromeClient;

/** Capacitor's chrome client (alerts, confirms, console) with Rentora's file chooser. */
public class RentoraChromeClient extends BridgeWebChromeClient {

    private final FileChooser fileChooser;

    public RentoraChromeClient(Bridge bridge, FileChooser fileChooser) {
        super(bridge);
        this.fileChooser = fileChooser;
    }

    @Override
    public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams params) {
        fileChooser.open(filePathCallback, params);
        return true;
    }
}
