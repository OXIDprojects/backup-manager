<?php
    //LICENSE_CHECK
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf8">

        <title>Oxid Backup</title>

        <link rel="stylesheet/less" href="gui/style.less">
        <link rel="stylesheet" href="gui/shepherd-theme-arrows.css">
        
        <script type="text/javascript">
            var oxid = oxid || {};
        </script>

        <script src="gui/libs/less-1.3.0.min.js"></script>

        <script src="gui/libs/jquery-1.7.2.min.js"></script>
        <script src="gui/libs/bootstrap/js/bootstrap-alert.js"></script>
        <script src="gui/libs/bootstrap/js/bootstrap-modal.js"></script>

        <script src="gui/libs/amplify.min.js"></script>
        <script src="gui/apiroutes.js"></script>

        <script src="gui/libs/shepherd.min.js"></script>

        <script src="gui/libs/knockout-2.1.0.js"></script>
        <script src="gui/libs/knockout-components.js"></script>
        <script src="gui/libs/knockout-validation.js"></script>
        <script src="gui/viewmodels/PasswordPanel.js"></script>
        <script src="gui/components/CheckBox/CheckBox.js"></script>
        <script src="gui/components/TreeView/TreeView.js"></script>
        <script src="gui/viewmodels/DatabaseSettings.js"></script>
        <script src="gui/viewmodels/NewBackupPanel.js"></script>
        <script src="gui/viewmodels/BrowseBackupsPanel.js"></script>
        <script src="gui/viewmodels/QuickBackupPanel.js"></script>
        <script src="gui/viewmodels/PrivateKeyPanel.js"></script>
        <script src="gui/viewmodels/main.js"></script>
    </head>
    <body>

        <!-- ko with: passwordPanel -->
        <div class="modalBackground">
            <div class="modalPanel metro-panel metro-blue">
                <h3 class="panel-heading" data-bind="text: title"></h3>

                <form class="nomargin">
                    <input type="password" id="password" data-bind="value: password">
                    <button type="submit" data-bind="click: submit">Submit</button>
                </form>
            </div>
        </div>
        <!-- /ko -->
        <!-- ko with: globalError -->
        <div class="modalBackground">
            <div class="alert modalPanel metro-panel metro-red">
                <h3 class="panel-heading" data-bind="text: title">Fehler!</h3> <span data-bind="text: text"></span>
            </div>
        </div>
        <!-- /ko -->

        <div id="container" class="container-fluid">

            <header><span class="blue">Oxid</span>Backup</header>

            <div class="row-fluid">
                <div id="sidebar" class="span3">
                    <!--button class="metro-panel active" data-bind="click: showBrowseBackupsPanel">
                            <h3 class="panel-heading">Browse backups</h3>
                            Browse, restore and remove the archived copies of your files and database.
                    </button-->
                    <button id="newBackupButton" class="metro-panel metro-blue active" data-bind="click: showNewBackupPanel">
                        <h3 class="panel-heading">Neues Backup</h3>
                        Erstellt einen neuen Job der in bestimmten Abständen ausgeführt wird.
                    </button>
                    <button id="quickBackupButton" class="metro-panel metro-green active" data-bind="click: showQuickBackupPanel">
                        <h3 class="panel-heading">Schnell Backup</h3>
                        Erstellen Sie ein schnelles Backup. Wird nur einmalig ausgeführt.
                    </button>
                    <button class="metro-panel metro-green active" data-bind="click: startTour">
                        <h3 class="panel-heading">Hilfe</h3>
                        Interaktive Hilfe Tour starten.
                    </button>
                    <div id="cronButton" class="metro-panel metro-red active">
                        <h3 class="panel-heading">Cron</h3>
                        Damit die Backup Jobs regelmäßig ausgeführt werden können müssen Sie folgenden Befehl als Cron alle 5 Minuten ausführen lassen:<br/><br/>
                        <pre>php <?php echo realpath(dirname(__FILE__)) . '/cron.php'; ?> > /dev/null 2>&1</pre>
                    </div>
                    <!--button class="metro-panel metro-red active">
                            <h3 class="panel-heading">Schnell Wiederherstellung</h3>
                            Stellen Sie ein Backup schnell wieder her. WIrd nur einmalig ausgeführt.
                    </button-->
                </div>
                <div id="content" class="span9 row">
                    <div id="alertsContainer" data-bind="foreach: alerts">
                        <div class="row">
                            <div class="alert metro-panel span12" data-bind="css: { 'metro-yellow': type == 'warning', 'metro-red': type == 'error', 'metro-green': type == 'success' }">
                                <button class="close" data-dismiss="alert">×</button>
                                <h3 class="panel-heading" data-bind="text: title">Achtung!</h3> <span data-bind="text: message"></span>
                            </div>
                        </div>
                    </div>

                    <!-- ko with: modalBox -->
                    <div id="modal-box" class="modal hide fade">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h3 data-bind="text: title"></h3>
                        </div>
                        <div class="modal-body">
                            <p data-bind="text: message"></p>
                        </div>
                        <div class="modal-footer">
                            <a href="#" class="btn" data-dismiss="modal" aria-hidden="true">Schließen</a>

                            <!-- ko foreach: buttons -->
                            <a href="#" class="btn" data-bind="text: text, click: click, css: styles"></a>
                            <!-- /ko -->
                        </div>
                    </div>
                    <!-- /ko -->

                    <!-- ko with: privateKeyPanel -->
                    <div id="private-key-panel" class="modal hide fade">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h3>Neuen Privaten Schlüssel hinzufügen (private key)</h3>
                        </div>
                        <div class="modal-body">
                            <form class="metro-form">
                                <fieldset>
                                    <div class="control-group">
                                        <label class="control-label" for="name">Schlüssel name</label>
                                        <div class="controls">
                                            <input type="text" class="input-xlarge" style="width: 97%;" id="name" data-bind="value: name">
                                            <p class="help-block">Name um die Schlüssel zu unterscheiden</p>
                                        </div>
                                    </div>
                                    <div class="control-group">
                                        <label class="control-label" for="data">Schlüssel Daten</label>
                                        <div class="controls">
                                            <textarea class="input-xlarge" style="width: 97%;height: 80px;" id="data" data-bind="value: data"></textarea>
                                            <p class="help-block">Fügen Sie den Inhalt des privaten Schlüssels (private key) hier ein</p>
                                        </div>
                                    </div>
                                </fieldset>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <a href="#" class="btn" data-dismiss="modal" aria-hidden="true">Schließen</a>
                            <a href="#" class="btn" data-bind="enable: isValid, click: submit">Neuer Schlüssel</a>
                        </div>
                    </div>
                    <!-- /ko -->

                    <!-- ko if: restoringBackup -->
                    <div class="row">
                        <img src="gui/img/bigLoader.gif" width="128" height="128" style="display: block;margin: 0 auto;margin-top: 30px" />
                        <h2 class="metro-heading" style="width: 100%;text-align: center;margin-top: 30px">Backup wird wiederhergestellt...</h2>
                        <h4 style="width: 100%;text-align: center;">Je nach Archivgröße kann dies einige Zeit dauern. <span style="color: red">Schließen Sie diese Seite nicht!</span></h4>
                    </div>
                    <!-- /ko -->

                    <!-- ko if: creatingBackup -->
                    <div class="row">
                        <img src="gui/img/bigLoader.gif" width="128" height="128" style="display: block;margin: 0 auto;margin-top: 30px" />
                        <h2 class="metro-heading" style="width: 100%;text-align: center;margin-top: 30px">Backup wird erstellt...</h2>
                        <h4 style="width: 100%;text-align: center;">Je nach Backupgröße kann dies einige Zeit dauern. <span style="color: red">Schließen Sie diese Seite nicht!</span></h4>
                    </div>
                    <!-- /ko -->

                    <!-- ko with: quickBackupPanel -->
                    <div class="row">
                        <button class="metro-back pull-left" data-bind="click: $root.back"></button>
                        <h2 class="metro-heading">Schnell Backup</h2>

                        <form class="form-horizontal metro-form">
                            <fieldset>
                                <legend>Quelle</legend>

                                <div class="control-group">
                                    <label class="control-label" for="source">Verzeichnisse</label>
                                    <div class="controls">
                                        <div id="source" data-bind="component: dirTree"></div>

                                        <p class="help-block">Wählen Sie die Verzeichnisse die Sie sichern möchten</p>
                                    </div>
                                </div>
                                <div class="control-group">
                                    <label class="control-label" for="ignores">Dateien ignorieren</label>
                                    <div class="controls">
                                        <input type="text" class="input-xlarge" id="ignores" data-bind="value: ignores">

                                        <p class="help-block">Die Dateinamen müssen mit einem Semikolon getrennt werden. Verwenden Sie * als Wildcard</p>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>
                                    Datenbank

                                    <button class="no-border" type="button" title="Datenbank sichern" data-bind="click: addDatabase, visible: !hasDatabase()">
                                        <img src="gui/img/add.png" class="metro-circle-small">
                                    </button>
                                    <button class="no-border" type="button" title="Datenbank nicht sichern" data-bind="click: removeDatabase, visible: hasDatabase">
                                        <img src="gui/img/cancel.png" class="metro-circle-small">
                                    </button>
                                </legend>

                                <div>
                                    <div class="control-group" data-bind="visible: !hasDatabase()">
                                        <div class="controls">
                                            <span>Um eine Datenbank zu sichern klicken Sie das Plus Icon oben</span>
                                        </div>
                                    </div>

                                    <div data-bind="with: database">
                                        <div class="control-group" data-bind="css: {error: host.hasModError}">
                                            <label class="control-label" for="dbhost">Host</label>
                                            <div class="controls">
                                                <input id="dbhost" type="text" class="input-xlarge" data-bind="value: host" />

                                                <p class="help-block" data-bind="visible: host.hasModError, text: host.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group" data-bind="css: {error: port.hasModError}">
                                            <label class="control-label" for="dbport">Port</label>
                                            <div class="controls">
                                                <input id="dbport" type="text" class="input-small" data-bind="value: port" />

                                                <p class="help-block" data-bind="visible: port.hasModError, text: port.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group" data-bind="css: {error: user.hasModError}">
                                            <label class="control-label" for="dbuser">Benutzer</label>
                                            <div class="controls">
                                                <input id="dbuser" type="text" class="input-xlarge" data-bind="value: user" />

                                                <p class="help-block" data-bind="visible: user.hasModError, text: user.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label" for="dbpassword">Passwort</label>
                                            <div class="controls">
                                                <input id="dbpassword" type="password" class="input-xlarge" data-bind="value: password" />
                                            </div>
                                        </div>

                                        <div class="control-group">
                                            <div class="controls">
                                                <button class="btn" type="button" data-bind="click: getTree, enabled: !isLoading()">
                                                    <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: isLoading">
                                                    <span data-bind="text: isLoading() ? 'Verbinde...' : 'Verbinden'">Verbinden</span>
                                                </button>
                                                <span style="color: red;" data-bind="text: connectionError"></span>
                                            </div>
                                        </div>

                                        <div class="control-group" data-bind="visible: tree() != null">
                                            <label class="control-label">Datenbank &amp; Tabellen</label>
                                            <div class="controls">
                                                <div id="dest2" data-bind="component: tree"></div>
                                                <p class="help-block">Wählen Sie die Datebanken oder Tabellen die Sie sichern möchten</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" data-bind="click: submit">Sichern und herunterladen</button>
                            </div>

                        </form>
                    </div>
                    <!-- /ko -->

                    <!-- ko with: newBackupPanel -->
                    <div class="row">
                        <button class="metro-back pull-left" data-bind="click: $root.back"></button>
                        <h2 class="metro-heading" data-bind="text: isEdit() ? 'Backup Job bearbeiten' : 'Backup Job anlegen'"></h2>

                        <form class="form-horizontal metro-form">
                            <fieldset>
                                <legend>Stammdaten</legend>
                                <div class="control-group">
                                    <label class="control-label" for="title">Titel</label>
                                    <div class="controls">
                                        <input type="text" class="input-xlarge" id="title" data-bind="value: title, enable: !isEdit()">
                                        <p class="help-block">Der Titel kann später nicht geändert werden</p>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend>
                                    Quelle

                                    <button id="addDirectories" class="no-border" type="button" title="Verzeichnisse sichern" data-bind="click: showDirTree, visible: !sourceTreeVisible()">
                                        <img src="gui/img/add.png" class="metro-circle-small">
                                    </button>
                                    <button class="no-border" type="button" title="Keine Verzeichnisse sichern" data-bind="click: hideDirTree, visible: sourceTreeVisible">
                                        <img src="gui/img/cancel.png" class="metro-circle-small">
                                    </button>
                                </legend>

                                <div>
                                    <div class="control-group" data-bind="visible: !sourceTreeVisible()">
                                        <div class="controls">
                                            <span>Um Dateien und Verzeichnisse zu sichern klicken Sie das Plus icon oben</span>
                                        </div>
                                    </div>

                                    <div data-bind="visible: sourceTreeVisible">
                                        <div class="control-group">
                                            <label class="control-label" for="source">Verzeichnisse</label>
                                            <div class="controls">
                                                <div id="source" data-bind="component: dirTree"></div>

                                                <p class="help-block">Wählen Sie die Verzeichnisse die Sie sichern möchten</p>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label" for="ignores">Dateien ignorieren</label>
                                            <div class="controls">
                                                <input type="text" class="input-xlarge" id="ignores" data-bind="value: ignores">

                                                <p class="help-block">Die Dateinamen müssen mit einem Semikolon getrennt werden. Verwenden Sie * als Platzhalter</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>
                                    Datenbank

                                    <button id="addDatabase" class="no-border" type="button" title="Backup database" data-bind="click: addDatabase, visible: !hasDatabase()">
                                        <img src="gui/img/add.png" class="metro-circle-small">
                                    </button>
                                    <button class="no-border" type="button" title="Do not backup database" data-bind="click: removeDatabase, visible: hasDatabase">
                                        <img src="gui/img/cancel.png" class="metro-circle-small">
                                    </button>
                                </legend>

                                <div>
                                    <div class="control-group" data-bind="visible: !hasDatabase()">
                                        <div class="controls">
                                            <span>Um eine Datenbank zu sichern klicken Sie das Plus icon oben</span>
                                        </div>
                                    </div>

                                    <div data-bind="with: database" id="databaseContainer">
                                        <div class="control-group" data-bind="css: {error: host.hasModError}">
                                            <label class="control-label" for="dbhost">Host</label>
                                            <div class="controls">
                                                <input id="dbhost" type="text" class="input-xlarge" data-bind="value: host" />

                                                <p class="help-block" data-bind="visible: host.hasModError, text: host.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group" data-bind="css: {error: port.hasModError}">
                                            <label class="control-label" for="dbport">Port</label>
                                            <div class="controls">
                                                <input id="dbport" type="text" class="input-small" data-bind="value: port" />

                                                <p class="help-block" data-bind="visible: port.hasModError, text: port.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group" data-bind="css: {error: user.hasModError}">
                                            <label class="control-label" for="dbuser">Benutzer</label>
                                            <div class="controls">
                                                <input id="dbuser" type="text" class="input-xlarge" data-bind="value: user" />

                                                <p class="help-block" data-bind="visible: user.hasModError, text: user.errorMessage"></p>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label" for="dbpassword">Passwort</label>
                                            <div class="controls">
                                                <input id="dbpassword" type="password" class="input-xlarge" data-bind="value: password" />
                                            </div>
                                        </div>

                                        <div class="control-group">
                                            <div class="controls">
                                                <button id="databaseConnect" class="btn" type="button" data-bind="click: getTree, enabled: !isLoading()">
                                                    <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: isLoading">
                                                    <span data-bind="text: isLoading() ? 'Verbinde...' : 'Verbinden'">Verbinden</span>
                                                </button>
                                                <span style="color: red;" data-bind="text: connectionError"></span>
                                            </div>
                                        </div>

                                        <div class="control-group" data-bind="visible: tree() != null">
                                            <label class="control-label">Datenbanken &amp; Tabellen</label>
                                            <div class="controls">
                                                <div id="dest2" data-bind="component: tree"></div>
                                                <p class="help-block">Wählen Sie die Datenbanken oder Tabellen die Sie sichern möchten</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend>Ziel</legend>

                                <div class="control-group">
                                    <label class="control-label" for="destType">Typ</label>
                                    <div class="controls">
                                        <select class="input-xlarge" id="destType" data-bind="value: destType">
                                            <option value="local">Lokal</option>
                                            <option value="ftp">FTP</option>
                                            <option value="sftp">Sicheres FTP (SFTP)</option>
                                            <option value="dropbox">Dropbox</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="visible: destType() == 'ftp' || destType() == 'sftp'">
                                    <label class="control-label" for="ftpHost">Host und Port</label>
                                    <div class="controls">
                                        <input type="text" class="input-xlarge" id="ftpHost" data-bind="value: ftpHost">
                                    </div>
                                </div>
                                <div class="control-group" data-bind="visible: destType() == 'ftp' || destType() == 'sftp'">
                                    <label class="control-label" for="ftpUser">Benutzer</label>
                                    <div class="controls">
                                        <input type="text" class="input-xlarge" id="ftpUser" data-bind="value: ftpUser">
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() == 'sftp'">
                                    <label class="control-label" for="sftpUsePrivateKey">Privater Schlüssel</label>
                                    <div class="controls">
                                        <label><input id="sftpUsePrivateKey" type="checkbox" data-bind="checked: sftpUsePrivateKey" /> Private Key Authentifizierung verwenden</label>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() == 'sftp' &amp;&amp; sftpUsePrivateKey()">
                                    <label class="control-label">Private Schlssel</label>
                                    <div class="controls">
                                        <select data-bind="
										visible: sftpPrivateKeyArray().length > 0,
										options: sftpPrivateKeyArray,
										value: sftpPrivateKey
                                                " class="input-large"></select>
                                        <button class="btn" type="button" data-bind="click: refreshSFTPKeys">
                                            <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: sftpPrivateKeyArraysLoading">
                                            Aktualisieren
                                        </button>
                                        <button class="btn" type="button" data-bind="click: newSFTPKey">
                                            Neuer Schlüssel
                                        </button>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() == 'ftp' || destType() == 'sftp'">
                                    <label class="control-label" for="ftpPassword"><span data-bind="text: sftpUsePrivateKey() ? 'Key passphrase' : 'Password'"></span></label>
                                    <div class="controls">
                                        <input type="password" class="input-xlarge" id="ftpPassword" data-bind="value: ftpPassword">
                                        <p class="help-block" data-bind="visible: sftpUsePrivateKey">Lassen Sie dieses Feld leer wenn Ihr privater Schlüssel kein Passwort benötigt</p>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() == 'ftp' || destType() == 'sftp'">
                                    <div class="controls">
                                        <button class="btn" type="button" data-bind="click: getFTPTree, enabled: !ftpLoading()">
                                            <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: ftpLoading">
                                            <span data-bind="text: ftpLoading() ? 'Verbinde...' : 'Verbinden'">Verbinden</span>
                                        </button>
                                        <span style="color: red;" data-bind="text: ftpError"></span>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() == 'dropbox'">
                                    <label class="control-label">Authorisierte Dropbox Konten</label>
                                    <div class="controls">
                                        <select data-bind="
										visible: dropboxAccounts().length > 0,
										options: dropboxAccounts,
										value: dropboxAccount,
										optionsText: function (item) { return item.info.display_name; },
										optionsValue: 'id'
                                                " class="input-large"></select>
                                        <button class="btn" type="button" data-bind="click: refreshDropbox">
                                            <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: dropboxLoading">
                                            Aktualisieren
                                        </button>
                                        <button class="btn" type="button" data-bind="click: newDropbox">
                                            <img width="20" height="20" src="gui/img/loader.gif" data-bind="visible: dropboxNewLoading">
                                            Neuer Account
                                        </button>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="visible: destType() == 'dropbox'">
                                    <label class="control-label">Hinweis</label>
                                    <div class="controls">
                                        Damit Sie das Backup in Ihrem Dropbox Account sichern können müssen Sie folgendes tun:
                                        <ol>
                                            <li>Klicken Sie oben auf "Neuer Account"</li>
                                            <li>Ein Popup oder ein neuer Tab mit der Dropbox Website erscheint. Dort müssen Sie sich einloggen und die Anfrage bestätigen. Manche Browser blockieren Popups, bitte erlauben Sie die Popups dann manuell.</li>
                                            <li>Anschließen klicken Sie auf den aktualisieren Button, wenn alles funktioniert hat dann können Sie den Account auswählen</li>
                                        </ol>

                                        <strong>Die Backups werden in &lt;ihrem Dropbox Ordner&gt;/Apps/SmartBackup/ abgelegt</strong>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="visible: destType() != 'dropbox'">
                                    <label class="control-label">Verzeichnis</label>
                                    <div class="controls">
                                        <div id="dest" data-bind="visible: destType() == 'local', component: dirTreeDestination"></div>
                                        <div id="dest2" data-bind="visible: destType() == 'ftp' || destType() == 'sftp', component: ftpDirTreeDestination"></div>

                                        <p class="help-block">Wählen Sie das Verzeichnis in dem die Backups gesichert werden sollen. Wenn das Verzeichnis rot markiert ist kann es sein das es nicht schreibbar ist. Wenn Sie Probleme haben wenden Sie sich bitte an Ihren Server Administrator oder an unseren Support.</p>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend>Zeit</legend>
                                <div class="control-group">
                                    <label class="control-label" for="type">Typ</label>
                                    <div class="controls">
                                        <select class="input-xlarge" id="type" data-bind="value: type">
                                            <option value="xhours">Alle X Stunden</option>
                                            <option value="daily">Täglich</option>
                                            <option value="xdays">Alle X Tage</option>
                                            <option value="weekly">Wöchentlich</option>
                                            <option value="monthly">Monatlich</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="visible: type() == 'monthly'">
                                    <label class="control-label" for="day">Datum</label>
                                    <div class="controls">
                                        <select class="input-small" id="day" data-bind="value: day">
                                            <option value="1">1</option><option value="2">2</option>
                                            <option value="3">3</option><option value="4">4</option>
                                            <option value="5">5</option><option value="6">6</option>
                                            <option value="7">7</option><option value="8">8</option>
                                            <option value="9">9</option><option value="10">10</option>
                                            <option value="11">11</option><option value="12">12</option>
                                            <option value="13">13</option><option value="14">14</option>
                                            <option value="15">15</option><option value="16">16</option>
                                            <option value="17">17</option><option value="18">18</option>
                                            <option value="19">19</option><option value="20">20</option>
                                            <option value="21">21</option><option value="22">22</option>
                                            <option value="23">23</option><option value="24">24</option>
                                            <option value="25">25</option><option value="26">26</option>
                                            <option value="27">27</option><option value="28">28</option>
                                            <option value="29">29</option><option value="30">30</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="visible: type() == 'weekly'">
                                    <label class="control-label" for="weekDay">Tag</label>
                                    <div class="controls">
                                        <select class="input-medium" id="weekDay" data-bind="value: weekDay">
                                            <option value="0">Montag</option>
                                            <option value="1">Dienstag</option>
                                            <option value="2">Mittwoch</option>
                                            <option value="3">Donnerstag</option>
                                            <option value="4">Freitag</option>
                                            <option value="5">Samstag</option>
                                            <option value="6">Sonntag</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="css: {error: xdays.hasModError}, visible: type() == 'xdays'">
                                    <label class="control-label" for="xdays">Anzahl Tage</label>
                                    <div class="controls">
                                        <input type="text" class="input-small" id="xdays" data-bind="value: xdays">

                                        <p class="help-block" data-bind="visible: xdays.hasModError, text: xdays.errorMessage"></p>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="css: {error: xhours.hasModError}, visible: type() == 'xhours'">
                                    <label class="control-label" for="xhours">Anzahl Stunden</label>
                                    <div class="controls">
                                        <input type="text" class="input-small" id="xhours" data-bind="value: xhours">

                                        <p class="help-block" data-bind="visible: xhours.hasModError, text: xhours.errorMessage"></p>
                                    </div>
                                </div>
                                <div class="control-group" data-bind="css: {error: time.hasModError}, visible: type() != 'xhours'">
                                    <label class="control-label" for="time">Zeit</label>
                                    <div class="controls">
                                        <input type="text" class="input-small" id="time" data-bind="value: time">

                                        <p class="help-block" data-bind="visible: time.hasModError, text: time.errorMessage"></p>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend id="moreSettingsLegend">Weitere Einstellungen</legend>

                                <div class="control-group">
                                    <!--label class="control-label" for="type">Type</label-->
                                    <div class="controls">
                                        <label><input type="checkbox" data-bind="checked: keeplastxenabled" /> Nur die letzten X Archive behalten</label>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="css: {error: keeplastx.hasModError}, visible: keeplastxenabled">
                                    <label class="control-label" for="keeplastx">Anzahl der Archive</label>
                                    <div class="controls">
                                        <input type="text" class="input-small" id="keeplastx" data-bind="value: keeplastx" />

                                        <p class="help-block" data-bind="visible: keeplastx.hasModError, text: keeplastx.errorMessage"></p>
                                    </div>
                                </div>

                                <div class="control-group">
                                    <!--label class="control-label" for="type">Type</label-->
                                    <div class="controls">
                                        <label><input type="checkbox" data-bind="checked: emailMe" /> Mich per Email benachrichtigen wenn das Backup fertig gestellt ist</label>
                                    </div>
                                </div>

                                <div class="control-group" data-bind="css: {error: email.hasModError}, visible: emailMe">
                                    <label class="control-label" for="email">Email</label>
                                    <div class="controls">
                                        <input type="text" class="input-xlarge" id="email" data-bind="value: email" />

                                        <p class="help-block" data-bind="visible: email.hasModError, text: email.errorMessage"></p>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-actions">
                                <button id="submitButton" type="submit" class="btn btn-primary" data-bind="enable: isValid, click: submit, text: isEdit() ? 'Änderungen speichern' : 'Job anlegen'"></button>
                            </div>
                        </form>
                    </div>
                    <!-- /ko -->
                    <!-- ko with: browseBackupsPanel -->
                    <div class="row">
                        <!--button class="metro-back pull-left" data-bind="click: $root.back"></button-->
                        <h2 class="metro-heading" id="activeJobsHeading">Aktive Backup Jobs</h2>

                        <!-- ko if: data().length > 0 -->
                        <div data-bind="foreach: data">
                            <h2 class="pull-left" data-bind="text: title"></h2>
                            <div class="jobActions">
                            <button class="no-border heading-option-first" type="button" title="Backup Job löschen"
                                    data-bind="click: removeConfirm"
                                    >
                                <img src="gui/img/delete.png" class="metro-circle-small">
                            </button>
                            <button class="no-border heading-option" type="button" title="Backup Einstellungen"
                                    data-bind="click: function() {$root.editBackup(title);}"
                                    >
                                <img src="gui/img/settings.png" class="metro-circle-small">
                            </button>
                            <button class="no-border heading-option" type="button" title="Backup jetzt erstellen"
                                    data-bind="click: backup"
                                    >
                                <img src="gui/img/play.png" class="metro-circle-small">
                            </button>
                            </div>
                            <div style="clear: both;"></div>

                            <!-- ko if: archives().length > 0 -->
                            <table class="table table-hover table-valign-middle">
                                <thead>
                                <th style="width: 30px">#</th>
                                <th>Date</th>
                                <th style="width: 70px">Größe</th>
                                <th style="width: 120px">Aktionen</th>
                                </thead>
                                <tbody data-bind="foreach: archives">
                                <td data-bind="text: $index() + 1"></td>
                                <td data-bind="text: date"></td>
                                <td data-bind="text: prettySize"></td>
                                <td>
                                    <button class="no-border" type="button" title="Löschen" data-bind="click: remove">
                                        <img src="gui/img/delete.png" class="metro-circle-small">
                                    </button>
                                    <button class="no-border" type="button" title="Download" data-bind="click: download, visible: !nodownload">
                                        <img src="gui/img/download.png" class="metro-circle-small">
                                    </button>
                                    <button class="no-border" type="button" title="Wiederherstellen" data-bind="click: restore">
                                        <img src="gui/img/rew.png" class="metro-circle-small">
                                    </button>
                                </td>
                                </tbody>
                            </table>
                            <!-- /ko -->

                            <!-- ko if: archives().length == 0 -->
                            <h4 style="clear: both;">Keine Backups vorhanden</h4>
                            <!-- /ko -->
                        </div>
                        <!-- /ko -->
                        <!-- ko if: data().length == 0 -->
                        <h2>Es wurden noch keine Backup Jobs angelegt</h2>
                        <!-- /ko -->
                    </div>
                    <!-- /ko -->
                    <!-- ko if: isIndex -->
                    <div class="row">

                    </div>
                    <!-- /ko -->
                </div>
            </div>
        </div>
    </body>
</html>