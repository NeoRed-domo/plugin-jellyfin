# Changelog

This file lists all notable changes to the Jellyfin plugin.

## [1.2.1] - 20-03-2026

🔧 **Bug Fixes**

* **Real-time widget**: title and media type now update in real time on clip changes (missing listeners)
* **Real-time cover art**: replaced base64 storage (too large for Jeedom events) with a lightweight proxy URL — cover art now updates instantly
* **Monitoring glow**: the green glow on the active clip now works on commercial broadcasts and cinema sessions
* **Double-fire race condition**: engine actions (launch, volume, lighting) no longer trigger twice, thanks to early cache write before slow HTTP calls

---

## [1.2.0] - 20-03-2026

🌟 **Audio Normalisation & Major Improvements**

### Audio Normalisation (LUFS)
* **Calibration**: built-in pink noise (-24 LUFS), measurement via ffmpeg, EBU R128 formula
* **Cinema profiles**: Night (-20 dB), Cinema (0 dB), THX (+10 dB), Manual (bypass)
* **Commercial profiles**: Mute, Quiet (-20 dB), Normal (0 dB), Loud (+5 dB), Manual (bypass)
* **Amplifier control**: volume automatically adjusted per clip with per-section offsets
* **Audio output type**: Amplifier (passthrough, AC3 DRC correction) or TV/PCM
* **Real-time profile change** during playback

### Session Improvements
* **Live monitoring**: animated green glow on the currently playing section and clip
* **Progress**: based on actual playback duration (not clip count)
* **Technical badges**: video resolution and audio codec displayed on each clip
* **Toggles**: individual enable/disable for sections and triggers
* **Loop counter** visible during commercial broadcasts

### Documentation & Translations
* **Complete documentation** in 4 languages (FR, EN, ES, DE)
* **305 translated strings** per language

### Notable Fixes
* Reliable chaining (playlist PlayNow + Jellyfin auto-advance)
* Accurate LUFS measurement (temp file instead of pipe, AC3 DRC correction)
* Double-fire on auto-advance (immediate cache write)
* Amp volume command saved human name instead of ID

---

## [1.1.0] - 14-03-2026

🎬 **Broadcast Sessions**

### Cinema Sessions
* **7 sections**: Preparation, Intro, Adverts, Trailers, Short Film, Sound Trailer, Film
* **Lighting moods**: Jeedom scenario per section and per film cue point
* **Calibratable film cue points**: pre-credits, credits 1, post-film 1, credits 2, post-film 2, end
* **Session editor**: accordion interface with colour-coded sections, trigger drag & drop

### Commercial Broadcasts
* **Looped playlist**: infinite, N times, or single playback
* **Automatic chaining** via Jellyfin playlist

### Widget
* **Session button** (🎬) to launch a session from the dashboard
* **Session list** with poster, duration and statistics

### Execution Engine
* Daemon polling at 0.25s for maximum responsiveness
* State machine (waiting for launch, playing, media ended)
* Jellyfin auto-advance detection (resync)
* Next clip warm-up (transcode pre-caching)
* HTTPS/HTTP video proxy for calibration

---

## [1.0.0] - 15-02-2026

🌍 **First Stable Release**

* **Multi-language**: plugin translated into English (en_US), German (de_DE) and Spanish (es_ES)
* **Fix**: widget library button
* **Fix**: PHP syntax on configuration page
* **Documentation**: links and structure for the Market

---

## [Beta] - 14-02-2026

🌟 **Media Library & Favourites**

* **Library browser**: navigation, search, media details
* **Favourites management**: add, quick launch, delete
* **Improved progress bar**
* **Smart filtering** of non-controllable clients

---

## [Beta] - 12-02-2026

🎉 **Initial Launch**

* Automatic player detection
* Media control: Play, Pause, Stop, Seek
* Real-time metadata and cover art
* Dashboard widget with interactive progress bar
* Python daemon for permanent connection
