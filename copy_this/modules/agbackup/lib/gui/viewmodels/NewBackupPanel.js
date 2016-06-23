function NewBackupPanel(data) {
	var self = this;

	var overwrite = false;

	if (typeof data !== 'undefined') {
		overwrite = true;
	}
	else {
		overwrite = false;

		data = {
			// Defaults
			title: "",
			ignores: "*.zip;*.tar;*.tar.gz;*.tmp;*.tgz;*.rar",
			type: "daily",
			time: "02:00",
			day: 0,
			weekDay: 0,
			xdays: 2,
			xhours: 12,
			keeplastxenabled: false,
			keeplastx: 5,
			destType: 'local',
			ftpHost: '',
			ftpPort: 21,
			ftpUser: '',
			ftpPassword: '',
			sftpUsePrivateKey: false,
			sftpPrivateKey: '',
			sourceIncluded: [],
			sourceExcluded: [],
			hasDatabase: false,
			databases: null,
			emailMe: false,
			email: '',
			dropboxAccount: ''
		};
	}

	this.isEdit = ko.observable(overwrite);

	this.title = ko.observable(data.title).extend({
        validation: {
            required: true,
            message: "Geben Sie einen Titel ein"
        }
    });

	this.ignores = ko.observable(data.ignores);

	this.type = ko.observable(data.type);
	this.time = ko.observable(data.time).extend({
        validation: {
            required: true,
			regex: /^[0-9]{2}\:[0-9]{2}$/,
            message: "Die Zeit muss im Format HH:MM eingegeben werden",
			func: function (value) {
				var time = $.trim(value).split(':');

				if (parseInt(time[0]) < 0 || parseInt(time[0]) >= 24)
					return "Fehlerhafte Stunde";

				if (parseInt(time[1]) < 0 || parseInt(time[1]) >= 60)
					return "Fehlerhafte Minuten";

				return true;
			}
        }
    });
	this.day = ko.observable(data.day);
	this.weekDay = ko.observable(data.weekDay);
	this.xdays = ko.observable(data.xdays).extend({
        validation: {
            required: true,
            message: "Die Tag Anzahl muss eine Zahl sein",
			func: function (value) {
				return !isNaN(value) && parseInt(value) > 0;
			}
        }
    });
	this.xhours = ko.observable(data.xhours).extend({
        validation: {
            required: true,
            message: "Die Stunden Anzahl muss eine Zahl sein",
			func: function (value) {
				return !isNaN(value) && parseInt(value) > 0;
			}
        }
    });

    this.keeplastxenabled = ko.observable(data.keeplastxenabled);
    this.keeplastx = ko.observable(data.keeplastx).extend({
        validation: {
            required: true,
            message: "Die Archiv Anzahl muss eine Nummer sein",
			func: function (value) {
				return !isNaN(value) && parseInt(value) > 0;
			}
        }
    });

    this.emailMe = ko.observable(data.emailMe);
    this.email = ko.observable(data.email).extend({
    	validation: {
    		required: true,
    		regex: /\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/,
    		message: "Das ist keine korrekte E-Mail Adresse"
    	}
    });


    this.databaseSettings = null;

    this.database = ko.observable(null);

    if (data.hasDatabase && data.database != null) {
        self.databaseSettings = new DatabaseSettings(data.database);
        this.database(self.databaseSettings);
    }

    this.hasDatabase = ko.computed(function() {
    	return self.database() !== null;
    });




    this.isValid = ko.computed(function () {
    	return self.time.isValid() && self.xhours.isValid() && self.xdays.isValid() && self.keeplastx.isValid() && (!self.hasDatabase() || self.database().isValid());
    });

	this.destType = ko.observable(data.destType);

	var appendPort = (data.ftpPort != 21 ? ':' + data.ftpPort : '');
	if (data.destType == 'sftp') {
		appendPort = (data.ftpPort != 22 ? ':' + data.ftpPort : '');
	}

	this.ftpHost = ko.observable((data.ftpHost || '') + appendPort);
	this.ftpUser = ko.observable(data.ftpUser || '');
	this.ftpPassword = ko.observable(data.ftpPassword || '');
	this.ftpLoading = ko.observable(false);
	this.ftpError = ko.observable('');

	this.sftpUsePrivateKey = ko.observable(data.sftpUsePrivateKey || false);
	this.sftpPrivateKey = ko.observable(data.sftpPrivateKey || '');
	this.sftpPrivateKeyArray = ko.observableArray([]);
	this.sftpPrivateKeyArraysLoading = ko.observable(false);

	this.refreshSFTPKeys = function (firstTime) {
		self.sftpPrivateKeyArraysLoading(true);

		$.getJSON('api/index.php?path=/privatekeys/getKeys&key=' + Application.password(), function (resultData) {
			self.sftpPrivateKeyArray(resultData);

			self.sftpPrivateKeyArraysLoading(false);

			if (firstTime === true) {
				self.sftpPrivateKey(data.sftpPrivateKey);
			}
		});
	};

	this.refreshSFTPKeys(true);

	this.newSFTPKey = function () {
		Application.privateKeyPanel(new PrivateKeyPanel());

		$('#private-key-panel').modal({
			keyboard: false
		});
	};

	this.dropboxLoading = ko.observable(false);
	this.dropboxNewLoading = ko.observable(false);
	this.dropboxAccounts = ko.observableArray([]);
	this.dropboxAccount = ko.observable(data.dropboxAccount);

	this.refreshDropbox = function () {
		self.dropboxLoading(true);

		$.getJSON('api/index.php?path=/dropbox/getAuthorizedAccounts&key=' + Application.password(), function (data) {
			self.dropboxAccounts(data);
			self.dropboxLoading(false);
		});
	};

	this.refreshDropbox();

	this.newDropbox = function () {
		self.dropboxNewLoading(true);
		$.getJSON('api/index.php?path=/dropbox/getAuthorizeUrl&key=' + Application.password(), function (data) {
			window.open(data.url, "_blank");//,"location=1,status=1,scrollbars=1,width=960,height=700");
			self.dropboxNewLoading(false);
		});
	};


	function mergeArrays(included, excluded) {
		var merged = [];

		if (typeof included !== "undefined")
		for (var i = 0; i < included.length; i++) {
			merged.push({path: included[i], include: true});
		}

		if (typeof excluded !== "undefined")
		for (var j = 0; j < excluded.length; j++) {
			merged.push({path: excluded[j], include: false});
		}

		merged.sort(function (a, b) {
			return a.path.length - b.path.length;
		});

		return merged;
	}

	this.loadTreeData = function(tree, included, excluded) {
		var merged = mergeArrays(included, excluded);
		var nodes = tree.data();

		function expandAndSelect(node, path, include, callback) {
			if (path.length === 0) {
				node.selected(include);

				if (typeof callback === "function")
					callback();

				return;
			}

			node.loadChildren(function() {
				var nodes = node.children();

				for (var i = 0; i < nodes.length; i++) {
					if (nodes[i].name() == path[0]) {
						path.splice(0, 1);

						expandAndSelect(nodes[i], path, include, callback);
						break;
					}
				}
			});
		}

		/*for (var i = 0; i < merged.length; i++) {
			var path = merged[i].path.replace(/^\//, '').replace(/\/+/, '/').replace(/\/$/, '').split('/');

			for (var j = 0; j < nodes.length; j++) {
				if (nodes[j].name() == path[0]) {
					path.splice(0, 1);

					expandAndSelect(nodes[j], path, merged[i].include);
					break;
				}
			}
		}*/
		function normalizePath(path) {
			return path.replace(/^\//, '').replace(/\/+/, '/').replace(/\/$/, '');
		}

		function clearSplit(path) {
			if (path.length == 1 && path[0].length == 0) {
				return [];
			}

			return path;
		}

		var loopMerged = merged;
		function loopOnMerged() {
			if (loopMerged.length === 0)
				return;

			var current = merged[0];
			var path = normalizePath(current.path);

			loopMerged.splice(0, 1);

			for (var j = 0; j < nodes.length; j++) {
				if (path.indexOf(normalizePath(nodes[j].path())) === 0) {
					path = clearSplit(normalizePath(path.replace(normalizePath(nodes[j].path()), '')).split('/'));

					expandAndSelect(nodes[j], path, current.include, loopOnMerged);
					break;
				}
			}
		}

		loopOnMerged();
	};
        
        this.treeView = new TreeView({
		url: 'api/index.php?path=/getDirTree&key=' + Application.password(),
		selectable: true,
		dirSelectMode: true,
		disabledNonReadable: true,
		loaded: function () {
			self.loadTreeData(self.dirTree(), data.sourceIncluded, data.sourceExcluded);
		}
	});

	this.dirTree = ko.observable(this.treeView);

	this.sourceTreeVisible = ko.observable(data.sourceIncluded.length > 0);

	this.showDirTree = function () {
		self.sourceTreeVisible(true);
                
                if(tour && tour.getById('third-step').isOpen()){
                    tour.next();
                }
	}

	this.hideDirTree = function () {
		self.sourceTreeVisible(false);
	}

	this.getSelected = function(tree) {
		var included = [],
			excluded = [];

		function getSelectedDirs(node, shouldInclude) {
			var shouldIncludeChildren = shouldInclude;

			if (shouldInclude && node.selected()) {
				included.push(node.path());
				shouldIncludeChildren = false;
			}
			else if (!shouldInclude && !node.selected()) {
				excluded.push(node.path());
				shouldIncludeChildren = true;
			}

			var children = node.children();
			for (var i = 0; i < children.length; i++) {
				getSelectedDirs(children[i], shouldIncludeChildren);
			}
		}

		if (typeof tree === "undefined" || tree === null)
			return {included: [], excluded: []};

		tree = tree.data();
		for (var i = 0; i < tree.length; i++) {
			getSelectedDirs(tree[i], true);
		}

		//console.log('included', included);
		//console.log('excluded', excluded);

		return {included: included, excluded: excluded};
	};



	this.dirTreeDestination = ko.observable(new TreeView({
		url: 'api/index.php?path=/getDirTree&key=' + Application.password(),
		selectable: true,
		dirSelectMode: true,
		singleSelection: true,
		disabledNonReadable: true,
		disabledNonWritable: true,
		loaded: function () {
			if (self.isEdit() && data.destType == 'local') {
				self.loadTreeData(self.dirTreeDestination(), [data.destDir], []);
			}
		}
	}));

	this.ftpDirTreeDestination = ko.observable();

	this.getFTPTree = function (included, excluded) {
		var treeview = new TreeView({
			url: 'api/index.php?path=/getDirTree&type=' + self.destType() + '&root=' + encodeURIComponent(self.ftpUser() + (self.ftpPassword() ? ':' + self.ftpPassword() : '') + '@' + self.ftpHost().replace(/^s?ftp\:\/\//, '') + '/') + (self.sftpUsePrivateKey() ? '&sftpKey=' + self.sftpPrivateKey() : '') + '&key=' + Application.password(),
			selectable: true,
			dirSelectMode: true,
			singleSelection: true,
			disabledNonReadable: true,
			disabledNonWritable: true,
			loaded: function () {
				if (self.isEdit() && (data.destType == 'ftp' || data.destType == 'sftp')) {
					self.loadTreeData(self.ftpDirTreeDestination(), [data.destDir], []);
				}
			},
			dataLoading: function () {
				self.ftpLoading(true);
				self.ftpError('');
			},
			dataLoaded: function () {
				self.ftpLoading(false);
				self.ftpError('');
			},
			dataError: function (target, isRoot) {
				self.ftpLoading(false);

				if (isRoot)
					self.ftpError("Kann nicht zum FTP Server verbinden, ist der Benutzer und das Kennwort korrekt?");
				else
					self.ftpError("Es gab ein Problem beim Empfangen der Daten");
			}
		});

		this.ftpDirTreeDestination(treeview);
	};

	if (self.isEdit() && (data.destType == 'ftp' || data.destType == 'sftp')) {
		this.getFTPTree();
	}


    this.addDatabase = function () {
        self.databaseSettings = new DatabaseSettings();
        self.database(self.databaseSettings);
        
        if(tour && tour.getById('seventh-step').isOpen()){
            tour.next();
        }
    };
    this.removeDatabase = function () {
    	self.databaseSettings = null;
        self.database(null);
    };

	this.submit = function() {
		var source = self.getSelected(self.dirTree());

		if (self.destType() == 'local') {
			self.ftpHost('');
			self.ftpPassword('');
			self.ftpUser('');
		}

		var db = {};
		if (self.hasDatabase()) {
			db = {
				host: self.database().host(),
				port: self.database().port(),
				user: self.database().user(),
				password: self.database().password(),

				databases: self.database().getSelected()
			};
		}

		var destDir = '';
		if (self.destType() == 'local')
		{
			destDir = self.getSelected(self.dirTreeDestination()).included[0];
		}
		else if (self.destType() == 'ftp' || self.destType() == 'sftp')
		{
			destDir = self.getSelected(self.ftpDirTreeDestination()).included[0];
		}

		if (self.destType() == 'dropbox' && typeof self.dropboxAccount() === 'undefined')
		{
			alert("Die Anwendung ist nicht für diese Dropbox authorisiert");
			return;
		}

		$.post('api/index.php?path=/backups/add&overwrite=' + (self.isEdit() ? 'true' : 'false') + '&key=' + Application.password(),
		{
			title: self.title(),
			sourceIncluded: self.sourceTreeVisible() ? source.included : [],
			sourceExcluded: self.sourceTreeVisible() ? source.excluded : [],
			ignores: self.ignores(),
			destType: self.destType(),
			destDir: destDir,
			ftpHost: self.ftpHost(),
			ftpUser: self.ftpUser(),
			ftpPassword: self.ftpPassword(),

			sftpUsePrivateKey: self.sftpUsePrivateKey(),
			sftpPrivateKey: self.sftpPrivateKey(),

			dropboxAccount: self.dropboxAccount(),

			type: self.type(),
			time: self.time(),
			day: self.day(),
			weekDay: self.weekDay(),
			xdays: self.xdays(),
			xhours: self.xhours(),
			keeplastxenabled: self.keeplastxenabled(),
			keeplastx: self.keeplastx(),

			emailMe: self.emailMe(),
			email: self.email(),

			hasDatabase: self.hasDatabase(),
			database: db

		}, function () {
			if (self.isEdit())
				Application.alert("Erfolg!", "Der Backup Job wurde aktualisiert.", 'success');
			else
				Application.alert("Erfolg!", "Der Backup Job wurde angelegt.", 'success');
                            
			Application.hideNewBackupPanel();
                        
		}).error(function (xhr) {
			if (xhr.status == 409)
				Application.alert("Fehler!", "Diesen Titel gibt es bereits. Es kann nicht 2 Jobs mit dem selben Titel geben", 'error');
			else if (xhr.status == 400)
				Application.alert("Error!", "Es wurden nicht alle felder ausgefüllt.", 'error');
			else
				Application.alert("Error!", "Es ist ein unbekannter Fehler aufgetreten.", 'error');
		});
                
                
	};
}