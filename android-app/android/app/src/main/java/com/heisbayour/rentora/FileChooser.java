package com.heisbayour.rentora;

import android.Manifest;
import android.app.Activity;
import android.content.ClipData;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.provider.MediaStore;
import android.webkit.MimeTypeMap;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient.FileChooserParams;
import androidx.activity.result.ActivityResult;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import java.io.File;
import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

/**
 * File inputs (transfer proofs, repair photos): one chooser offering the
 * camera and the gallery or files. The camera permission is asked for only
 * when a file input that takes images is opened; if it is refused the
 * gallery still works.
 */
class FileChooser {

    private final MainActivity activity;
    private ValueCallback<Uri[]> callback;
    private File cameraFile;
    private Uri cameraUri;

    FileChooser(MainActivity activity) {
        this.activity = activity;
    }

    void open(ValueCallback<Uri[]> filePathCallback, FileChooserParams params) {
        if (callback != null) {
            callback.onReceiveValue(null);
        }
        callback = filePathCallback;

        String[] mimeTypes = mimeTypes(params);
        boolean takesImages = acceptsImages(mimeTypes);
        boolean hasCamera = activity.getPackageManager().hasSystemFeature(PackageManager.FEATURE_CAMERA_ANY);

        if (!takesImages || !hasCamera) {
            launch(params, mimeTypes, false);
        } else if (ContextCompat.checkSelfPermission(activity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            launch(params, mimeTypes, true);
        } else {
            activity.requestPermissions(new String[] { Manifest.permission.CAMERA }, granted -> launch(params, mimeTypes, granted));
        }
    }

    private void launch(FileChooserParams params, String[] mimeTypes, boolean withCamera) {
        Intent pick = new Intent(Intent.ACTION_GET_CONTENT);
        pick.addCategory(Intent.CATEGORY_OPENABLE);
        if (mimeTypes.length == 1) {
            pick.setType(mimeTypes[0]);
        } else {
            pick.setType("*/*");
            if (mimeTypes.length > 1) {
                pick.putExtra(Intent.EXTRA_MIME_TYPES, mimeTypes);
            }
        }
        if (params.getMode() == FileChooserParams.MODE_OPEN_MULTIPLE) {
            pick.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        }

        Intent chooser = Intent.createChooser(pick, activity.getString(R.string.choose_file));
        cameraFile = null;
        cameraUri = null;
        if (withCamera) {
            Intent camera = cameraIntent();
            if (camera != null) {
                chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[] { camera });
            }
        }
        activity.launchForResult(chooser, this::deliver);
    }

    private Intent cameraIntent() {
        try {
            File dir = new File(activity.getCacheDir(), "camera");
            if (!dir.exists() && !dir.mkdirs()) {
                return null;
            }
            cameraFile = File.createTempFile("photo-", ".jpg", dir);
            cameraUri = FileProvider.getUriForFile(activity, activity.getPackageName() + ".fileprovider", cameraFile);
            Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
            camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
            camera.setClipData(ClipData.newRawUri("", cameraUri));
            camera.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION | Intent.FLAG_GRANT_READ_URI_PERMISSION);
            return camera;
        } catch (Exception e) {
            cameraFile = null;
            cameraUri = null;
            return null;
        }
    }

    private void deliver(ActivityResult result) {
        ValueCallback<Uri[]> target = callback;
        callback = null;
        if (target == null) {
            return;
        }

        List<Uri> uris = new ArrayList<>();
        if (result.getResultCode() == Activity.RESULT_OK) {
            if (cameraFile != null && cameraFile.length() > 0) {
                uris.add(cameraUri);
            } else if (result.getData() != null) {
                Intent data = result.getData();
                ClipData clip = data.getClipData();
                if (clip != null) {
                    for (int i = 0; i < clip.getItemCount(); i++) {
                        Uri uri = clip.getItemAt(i).getUri();
                        if (uri != null && !uri.equals(cameraUri)) {
                            uris.add(uri);
                        }
                    }
                } else if (data.getData() != null) {
                    uris.add(data.getData());
                }
            }
        }
        target.onReceiveValue(uris.isEmpty() ? null : uris.toArray(new Uri[0]));
    }

    /** accept="image/*,application/pdf" or ".jpg,.pdf" as MIME types; empty means anything. */
    private static String[] mimeTypes(FileChooserParams params) {
        Set<String> types = new LinkedHashSet<>();
        String[] accept = params.getAcceptTypes();
        if (accept != null) {
            for (String entry : accept) {
                for (String raw : entry.split(",")) {
                    String type = raw.trim().toLowerCase(Locale.US);
                    if (type.isEmpty()) {
                        continue;
                    }
                    if (type.startsWith(".")) {
                        type = MimeTypeMap.getSingleton().getMimeTypeFromExtension(type.substring(1));
                    }
                    if (type != null && type.contains("/")) {
                        types.add(type);
                    }
                }
            }
        }
        if (types.contains("*/*")) {
            return new String[] { "*/*" };
        }
        return types.isEmpty() ? new String[] { "*/*" } : types.toArray(new String[0]);
    }

    private static boolean acceptsImages(String[] mimeTypes) {
        for (String type : mimeTypes) {
            if (type.equals("*/*") || type.startsWith("image/")) {
                return true;
            }
        }
        return false;
    }
}
