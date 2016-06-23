function BrowseBackupsPanel() {
	var self = this;

	/* Properties */
	this.data = ko.observableArray([]);

	/* Methods */
	this.load = function () {
		$.getJSON('api/index.php?path=/backups/get&key=' + Application.password(), function(data) {
			for (var i = data.length - 1; i >= 0; i--) {
				var obj = data[i];

				obj.archives = ko.observableArray([]);
				obj.removeConfirm = (function(obj){
					return function () {
						self.removeConfirm(obj.title);
					};
				})(obj);
				obj.backup = (function(obj){
					return function () {
						self.backup(obj.title);
					}
				})(obj);
			};

			self.data(data);
			self.loadArchives();
		});
	};

	this.loadArchives = function(){
		for (var i = self.data().length - 1; i >= 0; i--) {
			var obj = self.data()[i];

			(function(obj){
				$.getJSON('api/index.php?path=/backups/getArchives&title=' + obj.title + '&key=' + Application.password(),
					function(data) {
						for (var i = data.length - 1; i >= 0; i--) {
							(function(archive) {
								archive.prettySize = ko.computed(function(){
									var i = 0;
									var byteUnits = [' bytes' , ' kB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB'];

									while (archive.size > 1024) {
										archive.size = archive.size / 1024;
										i++;
									}

									if (i > 0)
										return archive.size.toFixed(1) + byteUnits[i];
									else
										return archive.size + byteUnits[i];
								});

								archive.remove = function() {
									Application.showDialog('Sicher?', 'Wollen Sie dieses Archiv wirklich löschen?',
									[{
									text: 'Permanent löschen',
									styles: {'btn-danger': true},
									click: function() {
										$.getJSON('api/index.php?path=/archives/remove&title=' + encodeURIComponent(obj.title) + '&fileName=' + encodeURIComponent(archive.name) + '&key=' + Application.password(), function(info) {
											Application.alert("Erfolg!", "Das Archiv wurde gelöscht.", 'success');

											self.load();
										}).error(function (xhr) {
											//if (xhr.status == 400)
											Application.alert("Fehler!", "Es gab ein Problem beim löschen des Archivs!", 'error');
										});
									}
									}]);
								};

								archive.download = function() {
									window.location = 'api/index.php?path=/archives/download&title=' + encodeURIComponent(obj.title) + '&fileName=' + encodeURIComponent(archive.name) + '&key=' + Application.password();
								};

								archive.restore = function() {
									var archiveRestore = function(withDb, withFiles) {
										Application.showRestoringBackupPanel();

										$.getJSON('api/index.php?path=/archives/restore&title=' + encodeURIComponent(obj.title) + '&fileName=' + encodeURIComponent(archive.name) + '&database=' + (withDb ? 'true' : 'false') + '&files' + (withFiles ? 'true' : 'false') + '&key=' + Application.password(), function(info) {
											Application.alert("Erfolg!", "Das Archiv wurde wiederhergestellt.", 'success');

											Application.hideRestoringBackupPanel();
										}).error(function (xhr) {
											//if (xhr.status == 400)
											Application.alert("Fehler!", "Es gab ein Problem bei der Wiedreherstellung!", 'error');

											Application.hideRestoringBackupPanel();
										});
									};


									Application.showDialog('Sicher?', 'Wollen Sie dieses Archiv wirklich wiederherstellen? Die Dateien im Archiv werden die vorhandenen überschreiben, Dateien die nicht gesichert wurden werden nicht überschrieben. Bitte wählen Sie was Sie wiederherstellen möchten.',
									[{
										text: 'Nur Dateien',
										styles: {'btn-danger': true},
										click: function() {
											archiveRestore(false, true);
										}
									},
									{
										text: 'Nur Datenbank',
										styles: {'btn-danger': true},
										click: function() {
											archiveRestore(true, false);
										}
									},
									{
										text: 'Dateien UND Datenbank',
										styles: {'btn-danger': true},
										click: function() {
											archiveRestore(true, true);
										}
									}]);
								};
							})(data[i]);
						};

						/*data.sort(function(a, b) {
							return Date.parse(a.date) - Date.parse(b.date);
						});*/

						obj.archives(data);
                                                
                                                
                                                if(tour && tour.getById('fourteenth-step').isOpen()){
                                                    tour.next();
                                                }
					}
				);
			})(obj);
		};
	};

	this.removeConfirm = function (title) {
		Application.showDialog("Backup Job löschen?", "Wollen Sie diesen Job wirklich löschen? " +
			"Die bereits erstellen Archive werden nicht gelöscht.",
			[{
				text: 'Löschen',
				styles: {'btn-danger': true},
				click: function () {
					self.remove(title);
				}
			}]);
	};

	this.remove = function (title) {
		$.get('api/index.php?path=/backups/remove&title=' + title + '&key=' + Application.password(),
			function () {
				Application.alert("Erfolg!", "Der Backup Job wurde gelöscht.", 'success');

				self.load();
			}).error(function (xhr) {
				//if (xhr.status == 400)
				Application.alert("Fehler!", "Es gab ein Problem beim Löschen des Backups!", 'error');
			});
	};

	this.backup = function (title) {
		Application.showCreatingBackupPanel();

		$.getJSON('api/index.php?path=/backups/backup&title=' + title + '&key=' + Application.password(),
			function (data) {
				Application.alert("Erfolg!", "Das Archiv wurde erfolgreich erstellt.", 'success');

				for (var i = data.warnings.length - 1; i >= 0; i--) {
					Application.alert("Achtung!", data.warnings[i], 'warning');
				}

				Application.hideCreatingBackupPanel();
			}).error(function (xhr) {
				Application.alert("Fehler!", "Es gab ein Problem beim erstellen des Backups!", 'error');

				Application.hideCreatingBackupPanel();
			});
	};

	/* Constructor */
	this.load();
}