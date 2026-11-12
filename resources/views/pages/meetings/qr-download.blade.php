<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download meeting QR</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f6f8;
            color: #181c24;
        }
        .wrap {
            max-width: 520px;
            margin: 40px auto;
            padding: 24px;
            background: #fff;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
        }
        canvas { max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
        .hint { margin-top: 16px; color: #5a626e; font-size: 14px; }
        a.btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 16px;
            background: #EF4923;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
        }
        #qrHost { display: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1 style="font-size: 20px; margin: 0 0 8px;">Preparing QR download</h1>
        <p class="hint">Your branded PNG will download automatically.</p>
        <canvas id="cardCanvas" width="900" height="1200" aria-label="Meeting QR card"></canvas>
        <div>
            <a class="btn" id="manualDownload" href="#">Download PNG</a>
            <a class="btn" href="{{ route('meetings.show', $meeting) }}" style="background:#6b7280;">Back to meeting</a>
        </div>
        <div id="qrHost"></div>
    </div>

    <script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
    <script>
        (function () {
            var title = @json((string) $meeting->title);
            var when = @json(optional($meeting->scheduled_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'));
            var location = @json((string) ($meeting->location ?: ''));
            var token = @json((string) $token->token);
            var companyName = @json((string) ($companyName ?? 'your reseller account'));
            var logoUrl = @json(asset('assets/images/logo-icon.png'));
            var fileName = @json('feeder-meeting-qr-'.$meeting->uuid.'.png');

            var host = document.getElementById('qrHost');
            var canvas = document.getElementById('cardCanvas');
            var ctx = canvas.getContext('2d');
            var manual = document.getElementById('manualDownload');

            function drawCard(qrCanvas) {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#EF4923';
                ctx.fillRect(0, 0, canvas.width, 8);

                var y = 60;
                var maxTextWidth = canvas.width - 120;

                function fitText(text, size, weight) {
                    var value = String(text || '').replace(/\s+/g, ' ').trim();
                    if (!value) {
                        return '';
                    }
                    ctx.font = (weight || '600') + ' ' + size + 'px Arial, Helvetica, sans-serif';
                    if (ctx.measureText(value).width <= maxTextWidth) {
                        return value;
                    }
                    var ellipsis = '…';
                    var low = 0;
                    var high = value.length;
                    while (low < high) {
                        var mid = Math.ceil((low + high) / 2);
                        var candidate = value.slice(0, mid).trimEnd() + ellipsis;
                        if (ctx.measureText(candidate).width <= maxTextWidth) {
                            low = mid;
                        } else {
                            high = mid - 1;
                        }
                    }
                    return value.slice(0, low).trimEnd() + ellipsis;
                }

                function centerText(text, size, color, weight) {
                    var fitted = fitText(text, size, weight);
                    if (!fitted) {
                        return;
                    }
                    ctx.fillStyle = color;
                    ctx.font = (weight || '600') + ' ' + size + 'px Arial, Helvetica, sans-serif';
                    var width = ctx.measureText(fitted).width;
                    ctx.fillText(fitted, (canvas.width - width) / 2, y);
                }

                function finish(logoImage) {
                    if (logoImage) {
                        var targetH = 72;
                        var ratio = targetH / logoImage.height;
                        var targetW = logoImage.width * ratio;
                        ctx.drawImage(logoImage, (canvas.width - targetW) / 2, y, targetW, targetH);
                        y += targetH + 48;
                    } else {
                        y += 24;
                    }

                    centerText(title, 28, '#181c24', '700');
                    y += 42;
                    centerText(when, 22, '#5a626e', '500');
                    y += 34;
                    if (location) {
                        centerText(location, 20, '#5a626e', '500');
                        y += 36;
                    } else {
                        y += 12;
                    }

                    var qrSize = 480;
                    var qrX = (canvas.width - qrSize) / 2;
                    ctx.strokeStyle = '#181c24';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(qrX - 18, y - 18, qrSize + 36, qrSize + 36);
                    ctx.drawImage(qrCanvas, qrX, y, qrSize, qrSize);
                    y += qrSize + 50;
                    centerText('Present this code at check-in', 20, '#5a626e', '500');
                    y += 32;
                    centerText('Unique to ' + companyName, 16, '#5a626e', '400');

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            return;
                        }
                        var url = URL.createObjectURL(blob);
                        manual.setAttribute('href', url);
                        manual.setAttribute('download', fileName);
                        var anchor = document.createElement('a');
                        anchor.href = url;
                        anchor.download = fileName;
                        document.body.appendChild(anchor);
                        anchor.click();
                        anchor.remove();
                    }, 'image/png');
                }

                var logo = new Image();
                logo.onload = function () { finish(logo); };
                logo.onerror = function () { finish(null); };
                logo.src = logoUrl;
            }

            if (typeof QRCode === 'undefined') {
                document.querySelector('.hint').textContent = 'QR library failed to load.';
                return;
            }

            new QRCode(host, {
                text: token,
                width: 480,
                height: 480,
                correctLevel: QRCode.CorrectLevel.M
            });

            var qrCanvas = host.querySelector('canvas');
            if (!qrCanvas) {
                document.querySelector('.hint').textContent = 'Unable to render QR code.';
                return;
            }

            drawCard(qrCanvas);
        })();
    </script>
</body>
</html>
