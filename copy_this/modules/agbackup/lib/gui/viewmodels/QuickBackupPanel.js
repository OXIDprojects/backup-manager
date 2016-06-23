function QuickBackupPanel() {
	var self = this;

	this.dirTree = ko.observable(new TreeView({
		url: 'api/index.php?path=/getDirTree&key=' + Application.password(),
		selectable: true,
		dirSelectMode: true,
		disabledNonReadable: true
	}));

	this.ignores = ko.observable("*.zip;*.tar;*.tar.gz;*.tmp;*.tgz;*.rar");

	this.database = ko.observable(null);

	this.addDatabase = function () {
    	self.database(new DatabaseSettings());
    };
    this.removeDatabase = function () {
    	self.database(null);
    };

    this.hasDatabase = ko.computed(function() {
    	return self.database() !== null;
    });

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

    this.submit = function () {
    	Application.showCreatingBackupPanel();

    	var source = self.getSelected(self.dirTree());

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
	
		$.post('api/index.php?path=/quick/backup&key=' + Application.password(),
		{
			sourceIncluded: source.included,
			sourceExcluded: source.excluded,
			ignores: self.ignores(),

			hasDatabase: self.hasDatabase(),
			database: db
		},

		function (data) {
			Application.alert("Erfolg!", "Das Archiv wurde erfolgreich erstellt.", 'success');

			window.location = 'api/index.php?path=/quick/download&key=' + Application.password();

			setTimeout(function() {
				Application.hideCreatingBackupPanel();
			}, 1000);
		}).error(function (xhr) {
			Application.alert("Fehler!", "Es gab einen Fehler beim Erstellen des Backups!", 'error');

			Application.hideCreatingBackupPanel();
		});
    };
}