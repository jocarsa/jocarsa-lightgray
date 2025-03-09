/* transcription.js – Sincronización de la transcripción con el video */

var player;
var transcript = [];

// Cargar la transcripción desde la URL proporcionada (definida en index.php)
if (typeof transcriptionUrl !== 'undefined' && transcriptionUrl) {
    fetch(transcriptionUrl)
        .then(response => response.json())
        .then(data => {
            transcript = data;
        })
        .catch(error => {
            console.error('Error al cargar la transcripción:', error);
        });
}

function onYouTubeIframeAPIReady() {
    player = new YT.Player('ytplayer', {
        events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange
        }
    });
}

function onPlayerReady(event) {
    // Actualizar la transcripción cada 500ms
    setInterval(updateTranscript, 500);
}

function updateTranscript() {
    if (!player || player.getPlayerState() !== YT.PlayerState.PLAYING) {
        return;
    }
    var currentTime = player.getCurrentTime();
    // Buscar el segmento cuya marca de tiempo incluya el tiempo actual
    var currentSegment = transcript.find(function(segment) {
        return currentTime >= segment.start && currentTime <= segment.end;
    });
    var transcriptionDiv = document.getElementById('transcription');
    if (currentSegment) {
        transcriptionDiv.innerText = currentSegment.text;
    } else {
        transcriptionDiv.innerText = "";
    }
}

function onPlayerStateChange(event) {
    // Aquí puedes implementar comportamientos adicionales al cambiar de estado
}

// Insertar el script de la API de YouTube
var tag = document.createElement('script');
tag.src = "https://www.youtube.com/iframe_api";
var firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

