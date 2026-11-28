package com.heisbayour.rentora;

import android.content.ActivityNotFoundException;
import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Base64;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import android.widget.Toast;
import androidx.core.content.FileProvider;
import java.io.File;
import java.io.FileOutputStream;
import java.io.OutputStream;
import org.json.JSONObject;

/**
 * PDF downloads (receipts, invoices, statements) and the offline screen's Retry.
 *
 * The file is fetched by the page itself (fetch with credentials), so the
 * logged-in session and InfinityFree's browser-check cookie are sent exactly
 * as for any page. The bytes come back through this JavaScript interface, are
 * saved to Downloads and opened in the phone's PDF viewer.
 */
class NativeBridge {

    static final String NAME = "RentoraNative";

    private final MainActivity activity;

    NativeBridge(MainActivity activity) {
        this.activity = activity;
    }

    /** Called by the WebView's download listener (main thread). */
    void download(WebView webView, String url, String fileName, String mimeType) {
        if (!MainActivity.isOwnSite(url) || !MainActivity.isOwnSite(activity.currentUrl())) {
            activity.openExternal(Uri.parse(url));
            return;
        }
        Toast.makeText(activity, activity.getString(R.string.downloading, fileName), Toast.LENGTH_SHORT).show();
        String type = mimeType == null || mimeType.isEmpty() ? "application/octet-stream" : mimeType;
        String js =
            "(function(){" +
            "fetch(" + JSONObject.quote(url) + ",{credentials:'include'})" +
            ".then(function(r){" +
            "  if(!r.ok){throw new Error('HTTP '+r.status);}" +
            "  var t=(r.headers.get('content-type')||'').toLowerCase();" +
            "  if(t.indexOf('text/html')===0){throw new Error('not a file');}" +
            "  return r.blob();" +
            "})" +
            ".then(function(b){var f=new FileReader();f.onloadend=function(){" +
            "  var s=String(f.result);" + NAME + ".saveDownload(" + JSONObject.quote(fileName) + ",b.type||" + JSONObject.quote(type) + ",s.substring(s.indexOf(',')+1));" +
            "};f.readAsDataURL(b);})" +
            ".catch(function(e){" + NAME + ".downloadFailed(String(e&&e.message||e));});" +
            "})();";
        webView.evaluateJavascript(js, null);
    }

    @JavascriptInterface
    public void saveDownload(String fileName, String mimeType, String base64) {
        // Only the Rentora site may write files.
        if (!MainActivity.isOwnSite(activity.currentUrl())) {
            return;
        }
        try {
            byte[] bytes = Base64.decode(base64, Base64.DEFAULT);
            String name = safeName(fileName);
            Uri uri = Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q ? saveToDownloads(name, mimeType, bytes) : saveToAppFolder(name, bytes);
            activity.runOnUiThread(() -> open(uri, mimeType, name));
        } catch (Exception e) {
            downloadFailed(e.getMessage());
        }
    }

    /** Retry button on the offline screen: checks the connection, then reloads the page that failed. */
    @JavascriptInterface
    public void retry() {
        activity.runOnUiThread(activity::retryFromPage);
    }

    @JavascriptInterface
    public void downloadFailed(String reason) {
        activity.runOnUiThread(() -> Toast.makeText(activity, R.string.download_failed, Toast.LENGTH_LONG).show());
    }

    /** Android 10+: the public Downloads folder through MediaStore, no permission needed. */
    private Uri saveToDownloads(String name, String mimeType, byte[] bytes) throws Exception {
        ContentResolver resolver = activity.getContentResolver();
        ContentValues values = new ContentValues();
        values.put(MediaStore.Downloads.DISPLAY_NAME, name);
        values.put(MediaStore.Downloads.MIME_TYPE, mimeType);
        values.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS);
        values.put(MediaStore.Downloads.IS_PENDING, 1);
        Uri uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);
        if (uri == null) {
            throw new IllegalStateException("Could not create the file");
        }
        try (OutputStream out = resolver.openOutputStream(uri)) {
            if (out == null) {
                throw new IllegalStateException("Could not write the file");
            }
            out.write(bytes);
        }
        values.clear();
        values.put(MediaStore.Downloads.IS_PENDING, 0);
        resolver.update(uri, values, null, null);
        return uri;
    }

    /** Android 7 to 9: the app's own Downloads folder (no storage permission needed). */
    private Uri saveToAppFolder(String name, byte[] bytes) throws Exception {
        File dir = activity.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
        if (dir == null) {
            dir = new File(activity.getFilesDir(), "downloads");
        }
        if (!dir.exists() && !dir.mkdirs()) {
            throw new IllegalStateException("Could not create the folder");
        }
        File file = new File(dir, name);
        try (FileOutputStream out = new FileOutputStream(file)) {
            out.write(bytes);
        }
        return FileProvider.getUriForFile(activity, activity.getPackageName() + ".fileprovider", file);
    }

    private void open(Uri uri, String mimeType, String name) {
        Intent view = new Intent(Intent.ACTION_VIEW);
        view.setDataAndType(uri, mimeType);
        view.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
        try {
            activity.startActivity(view);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(activity, activity.getString(R.string.saved_to_downloads, name), Toast.LENGTH_LONG).show();
        }
    }

    private static String safeName(String name) {
        String clean = name == null ? "" : name.replaceAll("[\\\\/:*?\"<>|]", "_").trim();
        return clean.isEmpty() ? "rentora-download.pdf" : clean;
    }
}
