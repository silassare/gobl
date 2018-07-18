#!/usr/bin/env php
<?php
	/**
	 * This script is used to convert files in ORM/Sample to template files
	 */
	$sample_dir    = __DIR__ . '/../src/ORM/Sample/';
	$templates_dir = __DIR__ . '/../src/ORM/templates/';

	$map = [
		'MY_PROJECT_DB_NS'                      => '<%$.namespace%>',
		'MyTableQuery'                          => '<%$.class.query%>',
		'MyEntity'                              => '<%$.class.entity%>',
		'MyResults'                             => '<%$.class.results%>',
		'MyController'                          => '<%$.class.controller%>',
		'my_table'                              => '<%$.table.name%>',
		'//__GOBL_HEAD_COMMENT__'               => '<%@import(\'include/head.comment.otpl\',$)%>',
		'//__GOBL_RELATIONS_USE_CLASS__'        => '<%@import(\'include/entity.relations.use.class.otpl\',$)%>',
		'//__GOBL_COLUMNS_CONST__'              => '<%@import(\'include/entity.columns.const.otpl\',$)%>',
		'//__GOBL_RELATIONS_PROPERTIES__'       => '<%@import(\'include/entity.relations.properties.otpl\',$)%>',
		'//__GOBL_RELATIONS_GETTERS__'          => '<%@import(\'include/entity.relations.getters.otpl\',$)%>',
		'//__GOBL_COLUMNS_GETTERS_SETTERS__'    => '<%@import(\'include/entity.getters.setters.otpl\',$)%>',
		'//__GOBL_QUERY_FILTER_BY_COLUMNS__'    => '<%@import(\'include/query.filter.by.columns.otpl\',$)%>',
        '//__GOBL_JS_COLUMNS_CONST__'           => '<%@import(\'include/js.columns.const.otpl\',$)%>',
        '//__GOBL_JS_COLUMNS_GETTERS_SETTERS__' => '<%@import(\'include/js.getters.setters.otpl\',$)%>',
        '//__GOBL_JS_ENTITIES_CLASS_LIST__'     => '<%@import(\'include/js.entities.list.otpl\',$)%>',
        '//__GOBL_TS_COLUMNS_CONST__'           => '<%@import(\'include/ts.columns.const.otpl\',$)%>',
        '//__GOBL_TS_COLUMNS_GETTERS_SETTERS__' => '<%@import(\'include/ts.getters.setters.otpl\',$)%>',
        '//__GOBL_TS_ENTITIES_CLASS_LIST__'     => '<%@import(\'include/ts.entities.list.otpl\',$)%>',

		// for ozone service usage only
		'MY_PROJECT_SERVICE_NS'                 => '<%$.service.namespace%>',
		'MyOZService'                           => '<%$.service.class%>',
		'my_svc'                                => '<%$.service.name%>',
		'my_id'                                 => '<%$.pk.name%>',
		'\'my_pk_column_const\''                => '<%$.class.entity%>::<%$.pk.const%>',
		'//__OZONE_COLUMNS_NAME_MAP__'          => '<%@import(\'include/ozone.columns.name.map.otpl\',$)%>',
		'//__OZONE_RELATIONS_NAME_MAP__'        => '<%@import(\'include/ozone.relations.name.map.otpl\',$)%>'
	];

	$search      = array_keys($map);
	$replacement = array_values($map);

	$toTemplate = function ($from, $to) use ($search, $replacement) {
		$source = file_get_contents($from);
		$tpl    = str_replace($search, $replacement, $source);

		return file_put_contents($to, $tpl);
	};

	$toTemplate($sample_dir . 'Base/MyTableQuery.php', $templates_dir . 'base.query.class.otpl');
	$toTemplate($sample_dir . 'Base/MyEntity.php', $templates_dir . 'base.entity.class.otpl');
	$toTemplate($sample_dir . 'Base/MyResults.php', $templates_dir . 'base.results.class.otpl');
	$toTemplate($sample_dir . 'Base/MyController.php', $templates_dir . 'base.controller.class.otpl');

	$toTemplate($sample_dir . 'MyTableQuery.php', $templates_dir . 'query.class.otpl');
	$toTemplate($sample_dir . 'MyEntity.php', $templates_dir . 'entity.class.otpl');
	$toTemplate($sample_dir . 'MyResults.php', $templates_dir . 'results.class.otpl');
	$toTemplate($sample_dir . 'MyController.php', $templates_dir . 'controller.class.otpl');

	$toTemplate($sample_dir . 'MyOZService.php', $templates_dir . 'ozone.service.class.otpl');

	$toTemplate($sample_dir . 'js/JSBundle.js', $templates_dir . 'js.bundle.otpl');
	$toTemplate($sample_dir . 'js/MyEntity.js', $templates_dir . 'js.entity.class.otpl');

	$toTemplate($sample_dir . 'ts/TSBundle.ts', $templates_dir . 'ts.bundle.otpl');
	$toTemplate($sample_dir . 'ts/MyEntity.ts', $templates_dir . 'ts.entity.class.otpl');