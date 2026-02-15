<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('jellyfin');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>
<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i>  <?php echo __('Gestion', __FILE__); ?></legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span><?php echo __('Ajouter', __FILE__); ?></span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span><?php echo __('Configuration', __FILE__); ?></span>
            </div>
             <div class="cursor eqLogicAction logoSecondary" data-action="scanClients">
                <i class="fas fa-search"></i>
                <br>
                <span><?php echo __('Forcer Scan', __FILE__); ?></span>
            </div>
        </div>
        <legend><i class="fas fa-tv"></i>  <?php echo __('Mes Lecteurs Jellyfin', __FILE__); ?></legend>
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'opacity:0.3;';
                echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="text-align: center; background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
                echo "<br>";
                echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex">
            <span class="input-group-btn">
                <a class="btn btn-default btn-sm eqLogicAction" data-action="configure"><i class="fas fa-cogs"></i> <?php echo __('Configuration avancée', __FILE__); ?></a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i> <?php echo __('Dupliquer', __FILE__); ?></a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> <?php echo __('Sauvegarder', __FILE__); ?></a><a class="btn btn-danger btn-sm eqLogicAction" data-action="remove"><i class="fas fa-minus-circle"></i> <?php echo __('Supprimer', __FILE__); ?></a>
            </span>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> <?php echo __('Equipement', __FILE__); ?></a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> <?php echo __('Commandes', __FILE__); ?></a></li>
        </ul>
        <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x:hidden;">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <form class="form-horizontal">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo __('Nom de l\'équipement', __FILE__); ?></label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="<?php echo __('Nom de l\'équipement', __FILE__); ?>"/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label" ><?php echo __('Objet parent', __FILE__); ?></label>
                            <div class="col-sm-3">
                                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value=""><?php echo __('Aucun', __FILE__); ?></option>
                                    <?php
                                    foreach (jeeObject::all() as $object) {
                                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo __('Activer', __FILE__); ?></label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked /><?php echo __('Activer', __FILE__); ?></label>
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked /><?php echo __('Visible', __FILE__); ?></label>
                            </div>
                        </div>

                        <hr>
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo __('Device ID (Client)', __FILE__); ?></label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device_id" placeholder="<?php echo __('L\'ID du lecteur à surveiller', __FILE__); ?>" />
                                <span class="help-block"><?php echo __('Identifiant unique du lecteur Jellyfin (ex: 5d1e2f...)', __FILE__); ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo __('Afficher le liseré', __FILE__); ?></label>
                            <div class="col-sm-3">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="widget_border_enable" /><?php echo __('Activer', __FILE__); ?></label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label"><?php echo __('Couleur du liseré', __FILE__); ?></label>
                            <div class="col-sm-3">
                                <input type="color" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="widget_border_color" />
                                <span class="help-block"><?php echo __('Choisissez la couleur qui entourera le widget (Gris clair par défaut)', __FILE__); ?></span>
                            </div>
                        </div>

                    </fieldset>
                </form>
            </div>

            <div role="tabpanel" class="tab-pane" id="commandtab">
                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th><?php echo __('Nom', __FILE__); ?></th><th><?php echo __('Type', __FILE__); ?></th><th><?php echo __('Action', __FILE__); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'jellyfin', 'js', 'jellyfin'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>

<script>
    function printEqLogic(_eqLogic) {
        if (_eqLogic.configuration.widget_border_color == undefined || _eqLogic.configuration.widget_border_color == '') {
            $('.eqLogicAttr[data-l2key=widget_border_color]').val('#e5e5e5');
        }
        if (_eqLogic.configuration.widget_border_enable == undefined) {
            $('.eqLogicAttr[data-l2key=widget_border_enable]').prop('checked', false);
        }
    }
</script>