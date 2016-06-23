KnockoutComponents = {
	basePath: '',
	defaultSuffix: '.html'
};

ko.bindingHandlers.component = {
	update: function (element, valueAccessor, allBindingsAccessor, viewModel) {
		var options = ko.utils.unwrapObservable(valueAccessor());
		
		if (typeof options === "object" && options !== null && typeof options.render === "function") {
			
			options.element = element;
			options.loadView = function(view) {
				$(element).children().remove();
			
				$(element).load(KnockoutComponents.basePath + view + KnockoutComponents.defaultSuffix, function () {
					//var childBindingContext = bindingContext.createChildContext(viewModel);
					//ko.utils.extend(childBindingContext, newProperties);
					ko.applyBindingsToDescendants(options, element);
				});
			};
			
			options.render($(element));
		}
		
		return { controlsDescendantBindings: true };
	}
};