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
</form>