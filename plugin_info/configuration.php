<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-cogs"></i> {{Configuration du serveur Jellyfin}}</legend>
        
        <div class="form-group">
            <label class="col-sm-3 control-label">{{IP du serveur}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="jellyfin_ip" placeholder="192.168.1.10" />
                <span class="help-block">{{L'adresse IP de votre serveur Jellyfin (sans http://).}}</span>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Port du serveur}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="jellyfin_port" placeholder="8096" />
                <span class="help-block">{{Le port par défaut est souvent 8096.}}</span>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Clé API Jellyfin}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="jellyfin_apikey" placeholder="Clé générée dans Jellyfin" />
                <span class="help-block">{{Dans Jellyfin : Tableau de bord > Clés d'API.}}</span>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-folder-open"></i> {{Détection des types de média}}</legend>
        <div class="alert alert-info">
            {{Saisissez ici les mots-clés qui permettent d'identifier le type de média en fonction du chemin du fichier (dossier ou nom de fichier).}}
            <br/>
            {{Vous pouvez saisir plusieurs mots-clés séparés par des virgules (ex : "pub,intro"). Si l'un de ces mots est trouvé dans le chemin, le type sera appliqué.}}
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Films}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_movie" placeholder="film, movie" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Séries}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_series" placeholder="serie, show" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Audio / Musique}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_audio" placeholder="music, audio, album" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Publicités}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_ad" placeholder="pub, advert" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Bandes Annonces}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_trailer" placeholder="trailer, bande-annonce" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Sound Trailers}}</label>
            <div class="col-sm-3">
                <input class="configKey form-control" data-l1key="filter_sound_trailer" placeholder="jingle, dts, dolby" />
            </div>
        </div>

    </fieldset>

    <fieldset>
        <legend><i class="fas fa-lightbulb"></i> {{Ambiances lumineuses (défaut séances)}}</legend>
        <div class="alert alert-info">
            {{Sélectionnez un scénario Jeedom pour chaque ambiance lumineuse. Ces valeurs servent de défaut pour toutes les séances cinéma. Chaque séance peut surcharger ces valeurs individuellement.}}
        </div>

        <?php $scenarios = scenario::all(); ?>

        <legend style="font-size: 14px; margin-top: 10px;"><i class="fas fa-list"></i> {{Par section}}</legend>
        <?php
        $sectionSlots = [
            'lighting_preparation'   => 'Préparation',
            'lighting_intro'         => 'Intro',
            'lighting_pubs'          => 'Publicités',
            'lighting_trailers'      => 'Bandes annonces',
            'lighting_short_film'    => 'Court métrage',
            'lighting_audio_trailer' => 'Trailer audio',
            'lighting_film'          => 'Film'
        ];
        foreach ($sectionSlots as $key => $label) {
            echo '<div class="form-group">';
            echo '  <label class="col-sm-3 control-label">{{' . $label . '}}</label>';
            echo '  <div class="col-sm-3">';
            echo '    <select class="configKey form-control" data-l1key="' . $key . '">';
            echo '      <option value="">{{Aucun}}</option>';
            foreach ($scenarios as $scenario) {
                echo '      <option value="' . $scenario->getId() . '">' . $scenario->getHumanName() . '</option>';
            }
            echo '    </select>';
            echo '  </div>';
            echo '</div>';
        }
        ?>

        <legend style="font-size: 14px; margin-top: 10px;"><i class="fas fa-film"></i> {{Tops film}}</legend>
        <?php
        $markSlots = [
            'lighting_pre_generique' => 'Pré-générique',
            'lighting_generique_1'   => 'Générique 1',
            'lighting_post_film_1'   => 'Post film 1',
            'lighting_generique_2'   => 'Générique 2',
            'lighting_post_film_2'   => 'Post film 2',
            'lighting_fin'           => 'Fin'
        ];
        foreach ($markSlots as $key => $label) {
            echo '<div class="form-group">';
            echo '  <label class="col-sm-3 control-label">{{' . $label . '}}</label>';
            echo '  <div class="col-sm-3">';
            echo '    <select class="configKey form-control" data-l1key="' . $key . '">';
            echo '      <option value="">{{Aucun}}</option>';
            foreach ($scenarios as $scenario) {
                echo '      <option value="' . $scenario->getId() . '">' . $scenario->getHumanName() . '</option>';
            }
            echo '    </select>';
            echo '  </div>';
            echo '</div>';
        }
        ?>

        <legend style="font-size: 14px; margin-top: 10px;"><i class="fas fa-pause-circle"></i> {{Spécial}}</legend>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Pause (plein feu)}}</label>
            <div class="col-sm-3">
                <select class="configKey form-control" data-l1key="lighting_pause">
                    <option value="">{{Aucun}}</option>
                    <?php
                    foreach ($scenarios as $scenario) {
                        echo '<option value="' . $scenario->getId() . '">' . $scenario->getHumanName() . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-clock"></i> {{Timings d'enchaînement (séances)}}</legend>
        <div class="alert alert-info">
            {{Ces paramètres contrôlent la fluidité de l'enchaînement des médias pendant les séances. Ajustez si nécessaire selon votre lecteur.}}
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Pré-chargement média suivant (secondes)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="queue_anticipation" placeholder="2" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{NextTrack anticipé (secondes)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="next_anticipation" placeholder="0.5" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Timeout fallback PlayNow (secondes)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="fallback_timeout" placeholder="5" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Pause si lecteur disparu (secondes)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="player_lost_timeout" placeholder="10" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Arrêt si lecteur absent (secondes)}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="player_lost_max" placeholder="300" />
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-volume-up"></i> {{Normalisation audio — Offsets par section (dB)}}</legend>
        <div class="alert alert-info">
            {{Offset en dB appliqué à chaque section par rapport au volume de référence. 0 = même volume que la référence. Négatif = plus bas.}}
        </div>
        <?php
        $audioOffsets = [
            'audio_offset_preparation'   => ['Préparation', -12],
            'audio_offset_intro'         => ['Intro', -12],
            'audio_offset_pubs'          => ['Publicités', -12],
            'audio_offset_trailers'      => ['Bandes annonces', -8],
            'audio_offset_short_film'    => ['Court métrage', -4],
            'audio_offset_audio_trailer' => ['Trailer audio', 0],
            'audio_offset_film'          => ['Film', 0]
        ];
        foreach ($audioOffsets as $key => $info) {
            echo '<div class="form-group">';
            echo '  <label class="col-sm-3 control-label">{{' . $info[0] . '}}</label>';
            echo '  <div class="col-sm-2">';
            echo '    <div class="input-group">';
            echo '      <input class="configKey form-control" data-l1key="' . $key . '" placeholder="' . $info[1] . '" type="number" />';
            echo '      <span class="input-group-addon">dB</span>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        ?>
    </fieldset>

    <fieldset>
        <legend><i class="fas fa-sliders-h"></i> {{Compensation calibration}}</legend>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Compensation bruit rose → contenu (dB)}}</label>
            <div class="col-sm-2">
                <div class="input-group">
                    <input class="configKey form-control" data-l1key="audio_calibration_compensation" placeholder="4" type="number" />
                    <span class="input-group-addon">dB</span>
                </div>
            </div>
            <span class="col-sm-4 help-block">{{Le bruit rose sonne plus fort que du contenu réel au même LUFS. Ce paramètre compense la différence. Défaut : +4 dB.}}</span>
        </div>

        <legend><i class="fas fa-headphones"></i> {{Profils audio (dB)}}</legend>
        <div class="alert alert-info">
            {{Offset global appliqué sur tous les volumes. Cinéma = référence (0dB). Pilotable par scénario Jeedom via la commande du lecteur.}}
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Nuit}}</label>
            <div class="col-sm-2">
                <div class="input-group">
                    <input class="configKey form-control" data-l1key="audio_profile_night" placeholder="-20" type="number" />
                    <span class="input-group-addon">dB</span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Cinéma (référence)}}</label>
            <div class="col-sm-2">
                <div class="input-group">
                    <input class="configKey form-control" data-l1key="audio_profile_cinema" placeholder="0" type="number" />
                    <span class="input-group-addon">dB</span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{THX}}</label>
            <div class="col-sm-2">
                <div class="input-group">
                    <input class="configKey form-control" data-l1key="audio_profile_thx" placeholder="10" type="number" />
                    <span class="input-group-addon">dB</span>
                </div>
            </div>
        </div>
    </fieldset>
</form>