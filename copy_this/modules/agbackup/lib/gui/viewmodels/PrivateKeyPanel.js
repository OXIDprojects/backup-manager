function PrivateKeyPanel() {
	var self = this;

	this.name = ko.observable('').extend({
        validation: {
            required: true,
            message: "Dieses Feld wird benötigt"
        }
    });
	this.data = ko.observable('').extend({
        validation: {
            required: true,
            message: "Dieses Feld wird benötigt"
        }
    });

	this.isValid = ko.computed(function () {
    	return self.name.isValid() && self.data.isValid();
    });

	this.submit = function () {
		$.post('api/index.php?path=/privatekeys/addKey&key=' + Application.password(),
		{
			name: self.name(),
			data: self.data()
		},
		function () {
			$('#private-key-panel').modal('hide');
			Application.privateKeyPanel(null);

			if (Application.newBackupPanel() != null) {
				Application.newBackupPanel().refreshSFTPKeys();
			}
		}).error(function (xhr) {
			Application.alert("Fehler!", "Es gab einen unerwarteten Fehler während des Speicherns.", 'error');
		});
	};
}