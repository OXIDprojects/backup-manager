function CheckBox(checkedObservable, disabled) {
	var self = this;
	
	if (typeof defaultCheck === "undefined")
		defaultCheck = false;
	if (typeof disabled === "undefined")
		disabled = ko.observable(false);
	
	this.checked = checkedObservable;
	this.disabled = disabled;
	
	
	this.toggle = function() {
		if (!self.disabled())
			this.checked(!this.checked());
	};
	
	this.render = function() {
		this.loadView('CheckBox/tmpl');
	};
}