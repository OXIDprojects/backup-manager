var tour;

function SmartBackup() {
	var self = this;
        
	this.plainPassword = ko.observable();
	this.password = ko.computed({
		read: function () {
			return encodeURIComponent(self.plainPassword());
		},
		write: function (value) {
			self.plainPassword(value);
		}
	});
	this.alerts = ko.observableArray();

	this.modalBox = ko.observable(null);
	this.privateKeyPanel = ko.observable(null);

	/* Panels */
	this.passwordPanel = ko.observable();
	this.globalError = ko.observable();
	this.newBackupPanel = ko.observable(null);
	this.browseBackupsPanel = ko.observable(null);
	this.quickBackupPanel = ko.observable(null);
	this.restoringBackup = ko.observable(false);
	this.creatingBackup = ko.observable(false);
        
        this.currentBackupPanel = null;

	/* Methods */
	this.hidePasswordPanel = function () {
		self.passwordPanel(null);
		self.showBrowseBackupsPanel(true);
	};

	this.showNewBackupPanel = function () {
                self.hideBrowseBackupsPanel();
		self.hideRestoringBackupPanel(true);
		self.hideCreatingBackupPanel(true);
		self.hideQuickBackupPanel(true);
		self.currentBackupPanel = new NewBackupPanel(); 
                self.newBackupPanel( self.currentBackupPanel );
                
                if(tour && tour.getById('first-step').isOpen()){
                    tour.next();
                }
	};
	this.hideNewBackupPanel = function (limited) {
		self.newBackupPanel(null);

		if (!limited)
			self.showBrowseBackupsPanel();
	};
        
        this.startTour = function(){
            
           
            tour = new Shepherd.Tour({
              defaults: {
                classes: 'shepherd-theme-arrows',
                scrollTo: false
              }
            });
            
            tour.addStep('first-step', {
                text: 'Klicken Sie auf diese Schaltfläche<br/> um ein neues Backup anzulegen.',
                attachTo: '#newBackupButton left',
                advanceOn: '#newBackupButton click',
                buttons: [
                   {
                    text: 'Weiter',
                    action: this.showNewBackupPanel
                  } 
                ]
            });
            
            tour.addStep('second-step', {
                text: 'Geben Sie einen Titel ein, dieser muss<br/> eindeutig sein und wird verwendet<br/> um die Backup Jobs zu unterscheiden.',
                attachTo: '#title right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('third-step', {
                text: 'Klicken Sie auf diesen Button um Dateien zu dem Backup hinzuzufügen,<br/> ob Sie Dateien und/oder Datenbanken sichern wollen<br/> können Sie selbst bestimmen.',
                attachTo: '#addDirectories right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: function(){
                        self.currentBackupPanel.showDirTree();
                        //tour.next();
                    }
                  } 
                ]
            });
            
            tour.addStep('fourth-step', {
                text: 'Wählen Sie die Verzeichnisse die Sie sichern wollen,<br/> setzen Sie dazu die Checkbox neben dem jeweiligen Eintrag',
                attachTo: '#source right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('sixth-step', {
                text: 'Sie können auch einzelne Dateitypen oder Verzeichnisse ausschließen,<br/> geben Sie dazu die jeweiligen Dateinamen in dieses Feld ein. <br/>Der * wird als Platzhalter verwendet.',
                attachTo: '#ignores right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('seventh-step', {
                text: 'Klicken Sie hier um die Datenbank oder Teile davon mit zu sichern.',
                attachTo: '#addDatabase right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: function(){
                        self.currentBackupPanel.addDatabase();
                        //tour.next();
                    }
                  } 
                ]
            });
            
            tour.addStep('eigth-step',{
                text: 'Die Zugangsdaten zur Datenbank werden automatisch aus der Oxid<br/> Konfiguration gelesen, Sie können diese wenn nötig noch anpassen.',
                attachTo: '#databaseContainer right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('ninth-step',{
                text: 'Klicken Sie hier um die Verbindung zur Datenbank herzustellen.',
                attachTo: '#databaseConnect right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: function(){
                        self.currentBackupPanel.databaseSettings.getTree();
                        //tour.next();
                    }
                  } 
                ]
            });
            
            tour.addStep('tenth-step',{
                text: 'Nachdem die Verbindung hergestellt wurde sehen Sie hier die einzelnen Datenbanken und Tabellen.<br/> Wählen Sie aus was gesichert werden soll, klicken Sie dazu die Checkboxen neben den jeweiligen Tabellen oder Datenbanken.',
                attachTo: '#dest2 right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            var t = 'Wählen Sie nun wo das Backup landen soll, Sie können aus verschiedenen Zielen wählen:<br/><br/><ul>';
            t += '<li><strong>Lokal</strong> Sichert das Archiv auf dem Server, <br/>Sie können wählen in welches Verzeichnis gesichert werden soll</li>';
            t += '<li><strong>FTP</strong> Sichert das Archiv per FTP auf einem entfernten Server, <br/>Sie können die Zugangsdaten eingeben und wählen in welches Verzeichnis gesichert werden soll</li>';
            t += '<li><strong>SFTP</strong> Sichert das Archiv per SFTP (Secure FTP via SSH) auf einem entfernten Server, <br/>Sie können die Zugangsdaten eingeben und wählen in welches Verzeichnis gesichert werden soll</li>';
            t += '<li><strong>Dropbox</strong> Sichert das Archiv in einem Dropbox Account, <br/>Bitte beachten Sie die Hinweise wie man den Account dafür einrichtet.</li></ul>';
            
            tour.addStep('eleventh-step',{
                text: t,
                attachTo: '#destType right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('twelth-step',{
                text: 'Wählen Sie nun noch in welchem Zeitabstand das Backup ausgeführt werden soll.',
                scrollTo: true,
                attachTo: '#type right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('thirteenth-step',{
                text: 'Optional können Sie noch wählen wie viele der letzten Backups behalten werden<br/> sollen und ob Sie eine E-Mail erhalten wollen nachdem das Backup abgeschlossen wurde.',
                scrollTo: true,
                attachTo: '#moreSettingsLegend right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('fourteenth-step',{
                text: 'Klicken Sie nun noch auf Job anlegen um die Eingaben zu speichern.',
                scrollTo: true,
                attachTo: '#submitButton right',
                buttons: []
            });
            
            tour.addStep('fifteenth-step',{
                text: 'Alle angelegten Jobs werden hier vorne angezeigt, Sie können die Jobs hier <br/><strong>starten, löschen, das Archiv herunterladen oder ein Backup wiederherstellen</strong>. ',
                scrollTo: true,
                attachTo: '.jobActions right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('sixteenth-step',{
                text: 'Wenn Sie nur einmalig ein Backup erstellen und herunterladen wollen<br/> können Sie die Schnell Backup Funktion verwenden. Das Vorgehen ist<br/> analog zu den normalen Backup Jobs, das Backup kann jedoch nur <br/>heruntergeladen und nicht wiederhergestellt werden.',
                scrollTo: true,
                attachTo: '#quickBackupButton right',
                buttons: [
                   {
                    text: 'Weiter',
                    action: tour.next
                  } 
                ]
            });
            
            tour.addStep('seventeenth-step',{
                text: 'Damit ist das Hilfeprogramm abgeschlossen, bitte beachten <br/>Sie dass für die automatischen Backups ein Cron eingerichtet werden muss.<br/> Den Befehl dafür finden Sie hier, wie Sie Cron Jobs einrichten erfahren Sie von Ihrem Hoster.',
                scrollTo: true,
                attachTo: '#cronButton right',
                buttons: [
                   {
                    text: 'Ende',
                    action: tour.next
                  }
                ]
            });
            
            
            
            tour.start();
            
        };

	this.showQuickBackupPanel = function () {
		self.hideBrowseBackupsPanel();
		self.hideRestoringBackupPanel(true);
		self.hideCreatingBackupPanel(true);
		self.hideNewBackupPanel(true);
		self.quickBackupPanel(new QuickBackupPanel());
	};
	this.hideQuickBackupPanel = function (limited) {
		self.quickBackupPanel(null);

		if (!limited)
			self.showBrowseBackupsPanel();
	};

	this.showBrowseBackupsPanel = function () {
		self.hideNewBackupPanel(true);
		self.hideRestoringBackupPanel(true);
		self.hideCreatingBackupPanel(true);
		self.hideQuickBackupPanel(true);
		self.browseBackupsPanel(new BrowseBackupsPanel());
	};
	this.hideBrowseBackupsPanel = function () {
		self.browseBackupsPanel(null);
	};

	this.showRestoringBackupPanel = function () {
		self.hideNewBackupPanel(true);
		self.hideBrowseBackupsPanel();
		self.hideCreatingBackupPanel(true);
		self.hideQuickBackupPanel(true);

		self.restoringBackup(true);
	};
	this.hideRestoringBackupPanel = function (limited) {
		self.restoringBackup(false);

		if (!limited)
			self.showBrowseBackupsPanel();
	};

	this.showCreatingBackupPanel = function () {
		self.hideNewBackupPanel(true);
		self.hideBrowseBackupsPanel();
		self.hideRestoringBackupPanel(true);
		self.hideQuickBackupPanel(true);

		self.creatingBackup(true);
	};
	this.hideCreatingBackupPanel = function (limited) {
		self.creatingBackup(false);

		if (!limited)
			self.showBrowseBackupsPanel();
	};

	this.editBackup = function (title) {
		$.getJSON('api/index.php?path=/backups/get&title=' + title + '&key=' + self.password(), function (data) {
			self.newBackupPanel(new NewBackupPanel(data));

			self.hideBrowseBackupsPanel();
		});
	};

	this.back = function () {
		self.showBrowseBackupsPanel();
	};

	this.alert = function (title, message, type) {
		this.alerts.push({title: title, message: message, type: type});

		$('body').scrollTop(0);
	};

	this.showDialog = function (title, message, buttons) {
		function btnClick() {
			$('#modal-box').modal('hide');
		}

		for (var i = buttons.length - 1; i >= 0; i--) {
			buttons[i].click = (function(callback) {
				return function () {
					callback();

					btnClick();
				};
			})(buttons[i].click);
		};

		this.modalBox({
			title: title,
			message: message,
			buttons: buttons
		});

		$('#modal-box').modal({
			keyboard: false
		});
	};

	/* Computables */
	this.isIndex = ko.computed(function(){
		return self.newBackupPanel() === null && self.browseBackupsPanel() === null;
	});

	this.stats = ko.observable({
		freeSpace: 'n/a',
		backups: 'n/a',
		backupJobs: 'n/a',
		lastBackup: 'n/a'
	});

	/* Constructor */
	this.passwordPanel(new PasswordPanel());

	this.onLogin = function () {
		jQuery.getJSON('api/index.php?path=/checkInstall&key=' + self.password(), function (data) {
			var alerts = data.alerts;

			for (var i = alerts.length - 1; i >= 0; i--) {
				self.alert(alerts[i].title, alerts[i].message, alerts[i].type);
			}

			/*var stats = data.stats;
			stats.freeSpace = prettySize(stats.freeSpace);

			self.stats(stats);*/
		});
                
                jQuery.getJSON('api/index.php?path=/getOxidDatabase&key=' + self.password(), function (data) {
                    oxid.databaseSettings = data;
		});
	};
}

function prettySize(bytes)
{
	var i = 0;
	var byteUnits = [' bytes' , ' kB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB'];

	while (bytes > 1024) {
		bytes = bytes / 1024;
		i++;
	}

	if (i > 0)
		return bytes.toFixed(1) + byteUnits[i];
	else
		return bytes + byteUnits[i];
}

KnockoutComponents.basePath = 'gui/components/';
KnockoutComponents.defaultSuffix = '.html';

Application = new SmartBackup();
$(function($) {
	ko.applyBindings(Application);

	// Remove closed alerts from the Application.alerts array
	$('#alertsContainer .alert').live('closed', function () {
		Application.alerts.remove(ko.dataFor(this));
	});
});