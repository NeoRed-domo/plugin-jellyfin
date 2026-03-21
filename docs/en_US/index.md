# Jellyfin Plugin for Jeedom

## Description

The Jellyfin plugin provides advanced integration of a Jellyfin server into Jeedom. It offers full control of your players, real-time information feedback (media, cover art, progress), and automation of cinema sessions and commercial broadcasts.

### Main Features

- **Player control**: play, pause, stop, next, previous, seek
- **Real-time information**: title, duration, position, cover art, media type
- **Library browser**: browse and search your Jellyfin media library
- **Favourite shortcuts**: quick access to your favourite media from the widget
- **Cinema sessions**: automated chaining of clips by sections (intro, ads, trailers, film) with lighting moods and film cue points
- **Commercial broadcasts**: looped playback of media playlists
- **Audio normalisation**: LUFS calibration and automatic amplifier volume control
- **Audio profiles**: Night, Cinema, THX (cinema) / Mute, Quiet, Normal, Loud (commercial)
- **Multi-language**: French, English, Spanish, German

---

## Installation

### Prerequisites

- Jeedom version 4.4 or higher
- A Jellyfin server accessible from the local network
- A Jellyfin API key (generated in the Jellyfin Dashboard > API Keys)

### Plugin Installation

1. From the Jeedom Market, search for "Jellyfin" and install the plugin
2. Activate the plugin
3. Launch the dependency installation (Python 3, requests, ffmpeg)
4. Configure the plugin (see below)
5. Start the daemon

### Server Configuration

Access the plugin configuration page:

- **Server IP**: IP address of your Jellyfin server (without `http://`)
- **Server port**: Jellyfin port (default 8096)
- **Jellyfin API key**: key generated in Jellyfin > Dashboard > API Keys

---

## Media Type Detection

The plugin can identify the type of each media item by analysing the file path. Configure comma-separated keywords for each type:

- **Films**: e.g. `film, movie`
- **Series**: e.g. `serie, show`
- **Audio / Music**: e.g. `music, audio, album`
- **Adverts**: e.g. `pub, advert`
- **Trailers**: e.g. `trailer, bande-annonce`
- **Sound Trailers**: e.g. `jingle, dts, dolby`

---

## Jellyfin Players

### Automatic Detection

Players are automatically detected when they start playback on Jellyfin. A Jeedom device is created for each detected player.

### Player Configuration

Click on a player to access its configuration:

- **Device ID**: unique identifier of the player (automatically detected)
- **Show border**: enables a coloured frame around the widget
- **Border colour**: frame colour

#### Audio Configuration (optional)

- **Amplifier volume command**: select the Jeedom action/slider command that controls your amplifier's volume. Required for audio normalisation.
- **Default volume**: volume applied when no per-clip volume is defined (0-100)
- **Audio output type**:
  - *Amplifier (passthrough)*: the amplifier decodes the audio (DTS, AC3). Use this mode if your player sends the raw audio stream to the amplifier via HDMI.
  - *TV / PCM*: the client decodes the audio. Use this mode if the sound comes directly from the TV.
- **Amplifier volume info command**: Jeedom info command that reads the current amplifier volume. Optional, used for audio calibration.

### Available Commands

| Command | Type | Description |
|---------|------|-------------|
| Prev | action | Previous track (rewind if > 30s) |
| Play | action | Resume playback |
| Pause | action | Pause playback |
| Play/Pause | action | Toggle play/pause |
| Next | action | Next track |
| Stop | action | Stop playback |
| Title | info | Current media title |
| Status | info | Status (Playing/Paused/Stopped) |
| Duration | info | Total duration (HH:MM:SS) |
| Position | info | Current position |
| Remaining | info | Time remaining |
| Cover | info | Media cover art (HTML img) |
| Media Type | info | Detected media type |
| Set Position | action | Seek to a position (slider) |
| Profil audio cinéma | info | Active audio profile |
| Changer profil cinéma | action | Change profile (Nuit/Cinéma/THX/Manuel) |
| Profil audio commercial | info | Active commercial profile |
| Changer profil commercial | action | Change commercial profile |

---

## The Widget

The player widget displays playback information in real time:

- **Cover art** with blurred background
- **Title**, status and media type
- **Interactive progress bar** (click to seek)
- **Controls**: previous, play/pause, stop, next
- **Favourites button** (heart): opens the shortcuts panel
- **Sessions button** (film): opens the list of available sessions
- **Library button** (Jellyfin logo): opens the browser

### Library Browser

Click the Jellyfin logo to open the browser:

- Folder navigation with breadcrumb trail
- Search across the entire library
- Technical information (resolution, audio codec)
- Direct playback or add to favourites

### Favourite Shortcuts

The favourites panel provides quick access to your media:

- Add a favourite from the browser or from the widget (heart button on the currently playing media)
- Click a favourite to play it
- Remove a favourite with the ✕ button

---

## Cinema Sessions

### Concept

A cinema session is an automated sequence of media organised into sections, with lighting mood management and audio volume control.

### Creating a Session

1. Click **"Nouvelle séance"** on the plugin page
2. Choose **"Séance cinéma"** and give it a name
3. In the **Équipement** tab, select the target player
4. Switch to the **Séance** tab to configure the content

### Sections

A cinema session is composed of 7 sections, each identified by a colour:

| Section | Colour | Description |
|---------|--------|-------------|
| Préparation | Orange | Actions before the session (close shutters, turn on amplifier, etc.) |
| Intro | Purple | Introduction clips (logos, jingles) |
| Publicités | Red | Advertising spots |
| Bandes annonces | Cyan | Film trailers |
| Court métrage | Yellow | Short films |
| Trailer audio | Blue | Sound trailers (DTS, Dolby, etc.) |
| Film | Green | The main film |

### Triggers

Each section contains an ordered list of triggers:

- **Média**: a video clip from the Jellyfin library
- **Pause**: a waiting period (0 = unlimited pause, manual resume)
- **Action**: a Jeedom command or a Jeedom scenario

Triggers can be:
- **Reordered** with the ↑ ↓ arrows
- **Deleted** with the ✕ button
- **Enabled/Disabled** individually with the toggle
- **Edited**: click on the label of a pause or action to modify it

### Enable/Disable a Section

Each section has a toggle. A disabled section is skipped during playback.

### Film Cue Points (Calibration)

Cue points allow you to trigger lighting moods at specific moments during the film:

| Cue Point | Description |
|-----------|-------------|
| Pré-générique | The film starts to wind down |
| Générique 1 | Start of the first credits |
| Post film 1 | Post-credits scene |
| Générique 2 | Credits resume |
| Post film 2 | Second post-credits scene |
| Fin | End of the session |

To calibrate: add a film, click "Calibrer tops", use the built-in video player to mark the cue points.

### Lighting Moods

Each section and each cue point can trigger a Jeedom scenario. Configure defaults in the plugin configuration. Each session can override these values.

If the viewer pauses with the remote control, the "Pause" mood is triggered. When playback resumes, the mood for the current section is restored.

### Starting a Session

1. **From the editor**: "Lancer" button
2. **From the widget**: 🎬 button
3. **From a scenario**: `start` command

---

## Commercial Broadcasts

### Concept

A commercial broadcast is a media playlist played on loop, without sections or lighting moods.

### Loop Modes

- **No loop**: single playback
- **Infinite loop**: restarts indefinitely
- **Loop count**: loops N times then stops

---

## Audio Normalisation

### Concept

Normalisation analyses the volume of each clip (LUFS measurement) and automatically adjusts the amplifier volume for a consistent sound level. EBU R128 / Netflix / Spotify standard.

### Calibration

1. **"Calibration audio"** on the plugin page
2. Download and import the **pink noise** reference into Jellyfin (once only)
3. Select the player and the pink noise
4. Set your amplifier to the ideal volume and enter the value
5. Analyse the LUFS and save

### Normalising a Session

1. **"Normaliser le son"** button in the editor
2. Choose quick or full analysis
3. Auto volumes are calculated and applied

### Audio Profiles

| Cinema Profile | Offset | Commercial Profile | Offset |
|----------------|--------|--------------------|--------|
| Nuit | -20 dB | Muet | vol=0 |
| Cinéma | 0 dB | Discret | -20 dB |
| THX | +10 dB | Normal | 0 dB |
| Manuel | bypass | Fort | +5 dB |
| | | Manuel | bypass |

The "Manuel" profile completely disables volume control by the plugin.

---

## Jeedom Scenario Integration

### Available Commands

```
#[Salon][Séance Samedi][start]#     → Start the session
#[Salon][Séance Samedi][stop]#      → Stop
#[Salon][Séance Samedi][state]#     → State (stopped/playing/paused)
#[Salon][Séance Samedi][progress]#  → Progress (%)

#[Salon][Shield TV][set_audio_profile]# → Change cinema profile
#[Salon][Shield TV][set_commercial_audio_profile]# → Change commercial profile
```

---

## Troubleshooting

### The daemon does not start
Check the configuration (IP, port, API key) and dependencies.

### Clips do not chain together
Check the `jellyfin` logs in INFO mode. The daemon must be running.

### Normalisation does not work
ffmpeg must be installed. Calibration must be completed. The volume command must be configured on the player.

### Volume is too loud / too quiet
Adjust the per-section offsets, the pink noise compensation (+4 dB by default), or use the "Manuel" profile to take back manual control.

### Commands (play, pause, stop) do not work
If your Jellyfin is behind a **reverse proxy** (nginx, Apache, Caddy, Traefik...), you need to enable WebSocket forwarding. Without it, clients cannot establish a WebSocket connection to the server, and Jellyfin marks all sessions as non-controllable (`SupportsRemoteControl: false`).

- **Nginx Proxy Manager**: enable the "WebSocket Support" option in the proxy host configuration
- **Manual Nginx**: add the directives `proxy_set_header Upgrade $http_upgrade;` and `proxy_set_header Connection "upgrade";`
- **Apache**: enable the `mod_proxy_wstunnel` and `mod_rewrite` modules

After the change, restart your reverse proxy and refresh your Jellyfin clients.

### Equipment is not created
Equipment is automatically created when media is playing. If no equipment appears, check the `jellyfin` logs in Debug mode to see detected sessions and their status.
