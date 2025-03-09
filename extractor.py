import os
import json
from datetime import datetime
import requests
from yt_dlp import YoutubeDL

# ===== Configuration =====

# 1. Define the channel URL.
# It can be a URL like "https://www.youtube.com/channel/CHANNEL_ID" or "https://www.youtube.com/user/USERNAME".
# We append "/playlists" to list all playlists.
channel_url = "https://www.youtube.com/channel/UCsF0I4Sz5aPzDhVaCSDFJzw"  # <-- Change this to the desired channel URL
if not channel_url.endswith("/playlists"):
    channel_url = channel_url.rstrip("/") + "/playlists"

# Root folders for saving subtitles and thumbnails
subtitles_root = "subtitulos"
thumbnails_root = "miniaturas"

os.makedirs(subtitles_root, exist_ok=True)
os.makedirs(thumbnails_root, exist_ok=True)

# This dictionary will accumulate all playlists' video data.
output_data = {}

# ===== Step 1: Extract all playlists from the channel =====

# Use a flat extraction to quickly get the playlists list
ydl_opts_channel = {
    'quiet': True,
    'skip_download': True,
    'extract_flat': True,  # do not extract nested video info
    'forcejson': True,
}

with YoutubeDL(ydl_opts_channel) as ydl:
    channel_data = ydl.extract_info(channel_url, download=False)

# The channel_data should have an 'entries' key with all playlists
playlists = channel_data.get('entries', [])
if not playlists:
    print("No se encontraron playlists en el canal.")
    exit(1)

# ===== Step 2: Process each playlist =====

for playlist_entry in playlists:
    if playlist_entry is None:
        continue

    # Get the playlist id and construct its URL if needed.
    playlist_id = playlist_entry.get('id')
    if 'url' in playlist_entry:
        # Sometimes the URL is relative (e.g., "playlist?list=..."), so build the full URL.
        playlist_url = playlist_entry['url']
        if not playlist_url.startswith("http"):
            playlist_url = "https://www.youtube.com/" + playlist_url
    else:
        playlist_url = "https://www.youtube.com/playlist?list=" + playlist_id

    try:
        # Use full extraction for each playlist
        ydl_opts_playlist = {
            'quiet': True,
            'skip_download': True,
            'extract_flat': 'in_playlist',
            'writeautomaticsub': False,
            'writesubtitles': False,
            'writeinfojson': False,
            'forcejson': True,
        }
        with YoutubeDL(ydl_opts_playlist) as ydl:
            playlist_dict = ydl.extract_info(playlist_url, download=False)

        # Use the playlist title (cleaning up spaces and special characters for folder names)
        playlist_title = playlist_dict.get('title', f"Playlist_{datetime.now().strftime('%Y%m%d%H%M%S')}")
        playlist_name = playlist_title.replace(" ", "_").replace("/", "_")
        print("Procesando playlist:", playlist_name)

        # Create subfolders for subtitles and thumbnails for this playlist
        playlist_subtitles_folder = os.path.join(subtitles_root, playlist_name)
        playlist_thumbnails_folder = os.path.join(thumbnails_root, playlist_name)
        os.makedirs(playlist_subtitles_folder, exist_ok=True)
        os.makedirs(playlist_thumbnails_folder, exist_ok=True)

        playlist_data = []

        # ===== Step 3: Process each video within the playlist =====

        for entry in playlist_dict.get('entries', []):
            if entry is None:
                continue

            video_id = entry.get('id')
            video_url = f"https://www.youtube.com/watch?v={video_id}"
            video_title = entry.get('title', f"Video_{video_id}").replace(" ", "_").replace("/", "_")
            print("Procesando video:", entry.get('title', 'Unknown Title'))

            # Options for extracting detailed video information including subtitles and thumbnails
            video_opts = {
                'quiet': True,
                'skip_download': True,
                'writesubtitles': True,
                'writeautomaticsub': True,
                'subtitleslangs': ['es'],  # Attempt to download Spanish subtitles
                'writeinfojson': False,
                'forcejson': True,
            }

            try:
                with YoutubeDL(video_opts) as ydl_video:
                    video_info = ydl_video.extract_info(video_url, download=False)

                # ===== Subtitle extraction =====
                subtitles_file = os.path.join(playlist_subtitles_folder, f"{video_title}.txt")
                subtitles_available = False

                # Check for manually provided subtitles first...
                if 'subtitles' in video_info and 'es' in video_info['subtitles']:
                    subtitles = video_info['subtitles']['es']
                    if subtitles:
                        subtitle_url = subtitles[0].get('url')
                        if subtitle_url:
                            response = requests.get(subtitle_url)
                            if response.status_code == 200:
                                subtitle_srt = response.text
                                # Remove timestamps and sequence numbers
                                plain_text = ' '.join(
                                    [line for line in subtitle_srt.splitlines() if '-->' not in line and not line.strip().isdigit()]
                                )
                                if not os.path.exists(subtitles_file):
                                    with open(subtitles_file, "w", encoding="utf-8") as file:
                                        file.write(plain_text)
                                    print(f"Subtítulo guardado: {subtitles_file}")
                                else:
                                    print(f"Subtítulo ya existe: {subtitles_file}")
                                subtitles_available = True

                # If manual subtitles are not available, try automatic captions
                elif 'automatic_captions' in video_info and 'es' in video_info['automatic_captions']:
                    subtitles = video_info['automatic_captions']['es']
                    if subtitles:
                        subtitle_url = subtitles[0].get('url')
                        if subtitle_url:
                            response = requests.get(subtitle_url)
                            if response.status_code == 200:
                                subtitle_srt = response.text
                                plain_text = ' '.join(
                                    [line for line in subtitle_srt.splitlines() if '-->' not in line and not line.strip().isdigit()]
                                )
                                if not os.path.exists(subtitles_file):
                                    with open(subtitles_file, "w", encoding="utf-8") as file:
                                        file.write(plain_text)
                                    print(f"Subtítulo automático guardado: {subtitles_file}")
                                else:
                                    print(f"Subtítulo ya existe: {subtitles_file}")
                                subtitles_available = True

                if not subtitles_available:
                    subtitles_file = None
                    print(f"Subtítulos en español no disponibles para: {entry.get('title', 'Unknown Title')}")

                # ===== Thumbnail extraction =====
                thumbnail_url = video_info.get('thumbnail')
                thumbnail_file = os.path.join(playlist_thumbnails_folder, f"{video_title}.jpg")
                if thumbnail_url:
                    if not os.path.exists(thumbnail_file):
                        response = requests.get(thumbnail_url)
                        if response.status_code == 200:
                            with open(thumbnail_file, "wb") as file:
                                file.write(response.content)
                            print(f"Miniatura descargada: {thumbnail_file}")
                        else:
                            print(f"Error descargando miniatura para {entry.get('title', 'Unknown Title')}")
                    else:
                        print(f"Miniatura ya existe: {thumbnail_file}")
                else:
                    thumbnail_file = None
                    print(f"No se encontró miniatura para: {entry.get('title', 'Unknown Title')}")

                # ===== Collect video information =====
                playlist_data.append({
                    "video_title": entry.get('title', 'Unknown Title'),
                    "txt_file_path": subtitles_file,
                    "thumbnail_file_path": thumbnail_file,
                    "video_url": video_url,
                    "recorded_at": datetime.now().isoformat()
                })

            except Exception as e:
                print(f"Error procesando el video {entry.get('title', 'Unknown Title')}: {e}")

        # Save data for the current playlist in our output
        output_data[playlist_name] = playlist_data

    except Exception as e:
        print(f"Error procesando la playlist {playlist_url}: {e}")

# ===== Step 4: Save all collected video data to a JSON file =====

json_file_path = "playlists_data.json"
with open(json_file_path, "w", encoding="utf-8") as json_file:
    json.dump(output_data, json_file, indent=4, ensure_ascii=False)

print("Proceso completado. Archivo JSON actualizado:", json_file_path)
