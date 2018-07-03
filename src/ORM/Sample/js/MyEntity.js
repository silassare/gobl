{
	var MyEntity = gobl.MyEntity = function (data) {
		_i(this, data, MyEntity, "gobl.MyEntity");
	};

	MyEntity.prototype = {
		constructor: MyEntity,//__GOBL_JS_COLUMNS_GETTERS_SETTERS__
		toJSON     : function () { return this._getData();},
		hydrate    : function (data) { return _h(this, data);}
	};

//__GOBL_JS_COLUMNS_CONST__
}