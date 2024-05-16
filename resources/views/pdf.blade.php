<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Button with Icon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .btn {
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 10px;
        }

        .hide {
            display: none;
        }
        .audio-controls {
            margin-bottom: 10px;
            width: 50%;
        }
        .navigation {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            font-size: 18px;
        }

        .navigation a {
            text-decoration: none;
            color: #333;
        }
        .breadcrumb li+li:before {
            padding: 8px;
            color: black;
            content: "|";
        }
        .breadcrumb li a {
            color: #0275d8;
            text-decoration: none;
        }

        .breadcrumb li a:hover {
            color: #01447e;
            text-decoration: underline;
        }
        .btn-primary {
            background-color: blue;
            color: white;
        }

        .btn-warning {
            background-color: yellow;
            color: black;
        }

        .btn-success {
            background-color: green;
            color: white;
        }

        .btn-danger {
            background-color: red;
            color: white;
        }
    </style>
<body>

<header>
    <nav>
        <ul class="breadcrumb navigation">
            <li><a href="/">Home</a></li>
            <li>PDF Read out</li>
        </ul>
    </nav>
    <hr/>
</header>
<main>
<div style="display: flex;align-items: center;">
<div class="audio-controls">
    <button onclick="startReadOut()" id="read" class="btn btn-primary">Read Out</button>
    <button onclick="pauseReadOut()" id="pause" class="btn btn-warning hide">Pause</button>
    <button onclick="playReadOut()" id="play" class="btn btn-success hide">Play</button>
    <button onclick="stopReadOut()" id="stop" class="btn btn-danger hide">Stop</button>
    <div id="loader" class="hide">Loading...</div>
</div>
    <div class="audio-controls">
        <h1>PDF Read Out</h1>
    </div>
</div>
<object data="{{ Storage::url($pdf->path) }}" type="application/pdf" width="100%" height="500">
    <p>Your browser does not support PDFs.
        <a href="{{ Storage::url($pdf->path) }}" download>Download the PDF</a>.</p>
</object>
</main>
<footer>
<script>
    function startReadOut() {
        const loader = document.getElementById('loader');
        loader.style.display = 'block';
        fetch('/api/tts/convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ id: {{ $pdf->id }} })
        })
            .then(response => response.json())
            .then(data => {
                if (data.audio_path) {
                    const audio = new Audio(data.audio_path);
                    audio.play();
                    window.audioPlayer = {audio};
                    document.getElementById('read').style.display = 'none';
                    document.getElementById('pause').style.display = 'inline-block';
                    document.getElementById('stop').style.display = 'inline-block';
                    loader.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loader.style.display = 'none';
            });
    }

    function pauseReadOut() {
        if (window.audioPlayer && window.audioPlayer.audio) {
            window.audioPlayer.audio.pause();
            document.getElementById('pause').style.display = 'none';
            document.getElementById('play').style.display = 'inline-block';
        }
    }

    function playReadOut() {
        if (window.audioPlayer && window.audioPlayer.audio) {
            window.audioPlayer.audio.play();
            document.getElementById('play').style.display = 'none';
            document.getElementById('pause').style.display = 'inline-block';
        }
    }

    function stopReadOut() {
        if (window.audioPlayer && window.audioPlayer.audio) {
            const audio = window.audioPlayer.audio;
            audio.pause();
            audio.currentTime = 0;
            document.getElementById('stop').style.display = 'none';
            document.getElementById('read').style.display = 'inline-block';
            document.getElementById('pause').style.display = 'none';
            document.getElementById('play').style.display = 'none';
        }
    }
</script>
</footer>
</body>
</html>
