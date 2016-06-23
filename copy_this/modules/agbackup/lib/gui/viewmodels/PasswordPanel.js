function PasswordPanel() {
	var self = this;
	
	this.title = ko.observable("Passwort benötigt");
	this.password = ko.observable("");
	
	this.submit = function() {
		amplify.request({
			resourceId: "checkPassword",
			data: {
				password: self.password()	
			},
			success: function (data) {
				if (data.result) {
					Application.password(self.password());
					Application.onLogin();
					Application.hidePasswordPanel();
				} else {
					self.title('Das Passwort ist falsch');
				}
			},
			error: function (data) {
				Application.passwordPanel(null);
				Application.globalError({ title: "Verbindungsfehler!", text: "Es konnte keine Verbindung mit dem Server hergestellt werden. Versuchen Sie es später nochmal." });
			}
		});
	};
	
	/* Constructor */
	amplify.request({
		resourceId: "hasPassword",
		data: {},
		success: function (data) {
			if (!data.result) {
				Application.passwordPanel(null);
				Application.globalError({ title: "Einstellungsfehler!", text: "Bitte ändern Sie das Passwort in der Datei modules/agbackup/passwort.php" });
			}
		},
		error: function (data) {
			Application.passwordPanel(null);
			Application.globalError({ title: "Verbindungsfehler!", text: "Es konnte keine Verbindung mit dem Server hergestellt werden. Versuchen Sie es später nochmal." });
		}
	});
}