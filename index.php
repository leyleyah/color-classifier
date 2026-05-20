<?php
@ini_set('max_execution_time', '600');
@ini_set('default_socket_timeout', '300');
require_once __DIR__ . '/config.php';

$modelServerUp = false;
$fp = @fsockopen('127.0.0.1', 8765, $errno, $errstr, 1);
if ($fp !== false) {
    fclose($fp);
    $modelServerUp = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Color Classification AI</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h1>BASIC COLOR CLASSIFICATION SYSTEM</h1>
    <p class="subtitle">Upload an image to detect its dominant color.</p>

    <p class="hint <?php echo $modelServerUp ? 'hint-ok' : 'hint-warn'; ?>" id="serverHint">
        <?php if ($modelServerUp) { ?>
            
        <?php } else { ?>
            Model server is offline. Run <strong>start_model_server.bat</strong> (or <strong>start_all.bat</strong>) for fast results; otherwise the first prediction may take several minutes.
        <?php } ?>
    </p>

    <form id="predictForm" enctype="multipart/form-data">

        <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.jpg,.jpeg,.png" required>

        <div class="preview-box">
            <img id="previewImage" src="" alt="Preview" style="display:none;">
        </div>

        <button type="submit" id="predictBtn">Predict Color</button>

    </form>

    <div id="loadingOverlay" class="loading-overlay" hidden aria-live="polite">
        <div class="loading-card">
            <div class="spinner"></div>
            <p><strong>Running prediction…</strong></p>
            <p class="loading-sub" id="loadingSub">Please wait. This can take up to a few minutes if the model server is not running.</p>
        </div>
    </div>

    <div id="resultArea"></div>

</div>

<script>
(function () {
    const form = document.getElementById('predictForm');
    const input = document.getElementById('imageInput');
    const preview = document.getElementById('previewImage');
    const overlay = document.getElementById('loadingOverlay');
    const btn = document.getElementById('predictBtn');
    const resultArea = document.getElementById('resultArea');
    const loadingSub = document.getElementById('loadingSub');

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) {
            preview.style.display = 'none';
            preview.removeAttribute('src');
            return;
        }
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        resultArea.innerHTML =
            '<div class="result-box error-box">' +
            '<h2>Error</h2>' +
            '<p>' + escapeHtml(message) + '</p>' +
            '</div>';
        resultArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showResult(data) {
        const color = escapeHtml(data.color.charAt(0).toUpperCase() + data.color.slice(1));
        const conf = Number(data.confidence).toFixed(2);
        const hex = escapeHtml(data.hex || '#cccccc');
        let topHtml = '';

        if (data.note) {
            topHtml += '<p class="model-note multicolor-note">' + escapeHtml(data.note) + '</p>';
        }

        if (data.top_predictions && data.top_predictions.length) {
            topHtml = '<div class="top-list"><h3>Top predictions</h3><ul>';
            data.top_predictions.forEach(function (item) {
                topHtml +=
                    '<li><span class="mini-swatch" style="background-color:' + escapeHtml(item.hex) + ';"></span>' +
                    escapeHtml(item.color.charAt(0).toUpperCase() + item.color.slice(1)) +
                    ' — ' + Number(item.confidence).toFixed(2) + '%</li>';
            });
            topHtml += '</ul></div>';
        }

        if (data.dominant_colors && data.dominant_colors.length) {
            topHtml += '<div class="top-list"><h3>Colors in image (pixels)</h3><ul>';
            data.dominant_colors.forEach(function (item) {
                topHtml +=
                    '<li><span class="mini-swatch" style="background-color:' + escapeHtml(item.hex) + ';"></span>' +
                    escapeHtml(item.color.charAt(0).toUpperCase() + item.color.slice(1)) +
                    ' — ' + Number(item.confidence).toFixed(2) + '%</li>';
            });
            topHtml += '</ul></div>';
        }

        let imgHtml = '';
        if (data.image_url) {
            imgHtml =
                '<div class="uploaded-preview">' +
                '<p class="uploaded-label">Uploaded image</p>' +
                '<img src="' + escapeHtml(data.image_url) + '?t=' + Date.now() + '" alt="Uploaded">' +
                '</div>';
        }

        resultArea.innerHTML =
            '<div class="result-box">' +
            '<h2>Prediction Result</h2>' +
            '<div class="color-result">' +
            '<span class="color-swatch" style="background-color:' + hex + ';"></span>' +
            '<div><p class="main-prediction">' + color +
            '<span class="confidence">' + conf + '% confidence</span></p>' +
            '<p class="model-note">Detected using <code>color_classifier.h5</code></p></div></div>' +
            topHtml + '</div>' + imgHtml;

        resultArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const file = input.files[0];
        if (!file) {
            showError('Please choose an image first.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        overlay.hidden = false;
        btn.disabled = true;
        btn.textContent = 'Please wait…';
        resultArea.innerHTML = '';

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                showError('Server returned an invalid response. Is the PHP server still running?');
                return;
            }

            if (!data.success) {
                showError(data.error || 'Prediction failed.');
                return;
            }

            showResult(data);
        } catch (err) {
            showError('Network error: ' + (err.message || 'Could not reach the server.'));
        } finally {
            overlay.hidden = true;
            btn.disabled = false;
            btn.textContent = 'Predict Color';
        }
    });
})();
</script>

</body>
</html>
