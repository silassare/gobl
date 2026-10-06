<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * Run as its own process by ORMAutoloadCycleTest: a compile error cannot be caught.
 *
 * `generate` writes the ORM classes of one table into <dir>. `use` loads them as a framework does
 * that declares the namespace lazily: its autoloader declares the ORM when the namespace's first
 * class is asked for, and attaches a CRUD listener to the table then, all while that first class
 * is still loading: through a `CRUDEventProducer` keyed by the table (`producer`, the default), or
 * through the generated CRUD class (`generated`, which fails when that class is the one loading). Then it uses <first> (`query`, `entity`, `crud`,
 * `controller` or `results`) and prints "ok".
 *
 * Usage: php orm_autoload_cycle.php generate|use <dir> [first] [producer|generated]
 */

use Gobl\CRUD\CRUDEventProducer;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\CSGeneratorORM;

require __DIR__ . '/../../vendor/autoload.php';

const CYCLE_NAMESPACE = 'Gobl\Tests\Cycle\Db';

[, $mode, $dir] = $argv;
$first          = $argv[3] ?? 'query';
$listen         = $argv[4] ?? 'producer';

$schema = static function (): RDBMSInterface {
	$db = Db::newInstanceOf(SQLite::NAME, new DbConfig(['db_host' => ':memory:']));

	$db->ns(CYCLE_NAMESPACE)->table('cycle_events', static function (TableBuilder $tb): void {
		$tb->plural('cycle_events')->singular('cycle_event')->columnPrefix('event');
		$tb->id();
		$tb->string('title')->max(60);
	});

	return $db;
};

if ('generate' === $mode) {
	$db = $schema();

	(new CSGeneratorORM($db))->generate($db->getTables(CYCLE_NAMESPACE), $dir);

	exit(0);
}

$prefix   = CYCLE_NAMESPACE . '\\';
$declared = false;

\spl_autoload_register(static function (string $class) use ($dir, $prefix, $schema, $listen, &$declared): void {
	if (!\str_starts_with($class, $prefix)) {
		return;
	}

	if (!$declared) {
		$declared = true;
		$db       = $schema();

		$db->ns(CYCLE_NAMESPACE)->enableORM($dir);
		$db->lock();

		// What a CRUD listener does when it registers.
		$crud = 'generated' === $listen
			? \Gobl\Tests\Cycle\Db\CycleEventsCrud::new()
			: new CRUDEventProducer(CYCLE_NAMESPACE, 'cycle_events');

		$crud->onBeforeCreate(static fn (): bool => true);
	}

	$file = $dir . \DIRECTORY_SEPARATOR
		. \str_replace('\\', \DIRECTORY_SEPARATOR, \substr($class, \strlen($prefix))) . '.php';

	if (\is_file($file)) {
		require_once $file;
	}
});

match ($first) {
	'query'      => new \Gobl\Tests\Cycle\Db\CycleEventsQuery(),
	'entity'     => new \Gobl\Tests\Cycle\Db\CycleEvent(),
	'crud'       => \Gobl\Tests\Cycle\Db\CycleEventsCrud::new(),
	'controller' => new \Gobl\Tests\Cycle\Db\CycleEventsController(),
	'results'    => \class_exists(\Gobl\Tests\Cycle\Db\CycleEventsResults::class),
};

echo 'ok';
