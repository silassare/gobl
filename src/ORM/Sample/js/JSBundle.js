//__GOBL_HEAD_COMMENT__

(function (gobl) {
	"use strict";

	window.gobl = gobl;

	var
		// convert
		_c = function (v, type) {
			if (v !== null) {
				if (type === "bool") {
					v = !!parseInt(v);
				}
			}

			return v;
		},
		// hydrate
		_h = function (ctx, data) {
			var source_of_truth = ctx.toJSON();
			Object.keys(data).forEach(function (k) {
				if (k in source_of_truth) {
					ctx[k] = data[k];
				}
			});

			return ctx;
		},
		// init
		_i = function (ctx, original_data, entity, name) {
			if (!(ctx instanceof entity)) {
				throw new Error(name + " must be called with the new operator.");
			}

			var prefix  = entity["PREFIX"],
				columns = entity["COLUMNS"],
				_data   = {};

			ctx._getData = function () { return _data;};

			original_data = original_data || {};

			columns.forEach(function (col) {
				var short_name = col.slice(prefix.length + 1);

				_data[col]      = (col in original_data) ? original_data[col] : undefined;
				// makes columns available for other framework watcher like Vue.js
				ctx[short_name] = undefined;

				Object.defineProperty(ctx, short_name, {
					get: function () {
						return _data[col];
					},
					set: function (value) {
						_data[col] = value;
					}
				});
			});
		};

	//__GOBL_JS_ENTITIES_CLASS_LIST__
})(window["gobl"] || {});