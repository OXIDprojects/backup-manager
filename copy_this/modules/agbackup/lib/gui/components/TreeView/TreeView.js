function TreeView(options) {
	var self = this;
	
	if (typeof options.selectable === "undefined")
		options.selectable = false;
	if (typeof options.dirSelectMode === "undefined")
		options.dirSelectMode = false;
	if (typeof options.singleSelection === "undefined")
		options.singleSelection = false;
	
	this.options = options;
	this.selectable = ko.observable(options.selectable);
	this.dirSelectMode = ko.observable(options.dirSelectMode);
	this.singleSelection = ko.observable(options.singleSelection);
	this.disabledNonReadable = ko.observable(options.disabledNonReadable);
	this.disabledNonWritable = ko.observable(options.disabledNonWritable);
	
	function useData(data) {
		var mapped = [];
		for (var i = 0; i < data.length; i++) {
			mapped.push(new TreeNode(data[i], self, null));
		}
		
		self.data(mapped);
		
		if (typeof options.dataLoaded === "function") {
			options.dataLoaded();
		}
		
		if (typeof options.loaded === "function") {
			options.loaded();
		}
	}
	
	if (typeof options.data !== "undefined") {
	
		useData(options.data);
		
	}
	else if (typeof options.url !== "undefined") {
		if (typeof options.dataLoading === "function") {
			options.dataLoading();
		}
		
		$.getJSON(options.url, function (data) {
			useData(data);
		}).error(function () {
			if (typeof options.dataError === "function") {
				options.dataError(self, true);
			}
		});
		
	}
	
	this.data = ko.observableArray([]);
	
	this._clearingSelection = false;
	this.clearSelection = function () {
		if (this._clearingSelection)
			return;
		
		this._clearingSelection = true;
		var data = this.data();
		
		for (var i = 0; i < data.length; i++) {
			data[i].selected(false);
		}
		
		this._clearingSelection = false;
	};
	
	this.render = function () {
		this.loadView("TreeView/tmpl");
	};
}

function TreeNode(data, treeView, parent) {
	var self = this;
	
	this.tree = treeView;
	this.parent = parent;
	this.name = ko.observable(data.name);
	this.readable = ko.observable(data.readable);
	this.writable = ko.observable(data.writable);
	this.path = ko.observable(data.path);
	
	this.disabled = ko.computed(function() {
		return (treeView.disabledNonReadable() && !self.readable()) || (treeView.disabledNonWritable() && !self.writable());
	});
	
	/* Children init */
	if (typeof data.children === "undefined" || data.children === null) {
		this.children = ko.observableArray([]);
	}
	else {
		var mapped = [];
		for (var i = 0; i < data.children.length; i++) {
			mapped.push(new TreeNode(data.children[i], treeView, this));
		}
		
		this.children = ko.observableArray(mapped);
	}
	
	if (typeof data.hasChildren !== "undefined") {
		this.hasChildren = ko.observable(data.hasChildren);
	}
	else {
		this.hasChildren = ko.computed(function() {
			return self.children().length > 0;
		});
	}
	/* END Children init */
	
	var isSelected = ko.observable(false);
	this.selected = ko.computed({
		read: function() {
			if (isSelected())
				return isSelected();
			
			var children = self.children(), hasFalse = false, hasTrue = false;
			
			for (var i = 0; i < children.length; i++) {
				var selected = children[i].selected();
				
				if (selected === null)
					return null;
				else if (selected)
					hasTrue = true;
				else
					hasFalse = true;
					
				if (hasFalse && hasTrue)
					return null;
			}
			
			if (treeView.dirSelectMode())
				return hasTrue ? null : false;
			else
				return hasTrue;
		},
		write: function(value) {
			if (value === null)
				return;
			
			if (treeView.singleSelection()) {
				treeView.clearSelection();
			}
			
			if (treeView.dirSelectMode() || self.children().length === 0) {
				isSelected(value);
				
				if (typeof treeView.options.onChange === "function") {
					treeView.options.onChange();
				}
			}

			if (!treeView.singleSelection() || !value) {
				for (var i = 0; i < self.children().length; i++) {
					self.children()[i].selected(value);
				}
			}
		}
	});
	
	this.childrenVisible = ko.observable(false);
	this.loadChildren = function (callback) {
		if (typeof data.childrenUrl === "undefined" || !self.hasChildren() || self.children().length > 0) {
			
			if (typeof callback === "function") {
				callback();
			}
			
			return;
		}
		
		if (typeof treeView.options.dataLoading === "function") {
			treeView.options.dataLoading(self);
		}
		
		$.getJSON(data.childrenUrl, function(children) {
			if (typeof treeView.options.dataLoaded === "function") {
				treeView.options.dataLoaded(self);
			}
			
			if (children.length === 0) {
				self.hasChildren(false);
				self.childrenVisible(false);
			}
			else {
				var mapped = [];
				for (var i = 0; i < children.length; i++) {
					var treeNode = new TreeNode(children[i], treeView, self);
						
					if (!treeView.singleSelection() && isSelected())
						treeNode.selected(true);
						
					mapped.push(treeNode);
				}
					
				if (!treeView.dirSelectMode() && self.hasChildren())
					isSelected(false);
					
				self.children(mapped);
				self.childrenVisible(true);
			}
			
			if (typeof callback === "function") {
				callback();
			}
		}).error(function () {
			if (typeof treeView.options.dataError === "function") {
				treeView.options.dataError(self);
			}
		});
	};
	this.toggleChildren = function () {
		// Load data from url
		if (typeof data.childrenUrl !== "undefined" && self.hasChildren() && self.children().length === 0) {
			this.loadChildren();
		}
		else {
			this.childrenVisible(!this.childrenVisible());
		}
	};
}