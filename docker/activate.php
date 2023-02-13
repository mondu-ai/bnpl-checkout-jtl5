<?php

declare(strict_types=1);

use JTL\Shop;
use JTL\Backend\Wizard\WizardIO;

require_once __DIR__ . '/admin/includes/admininclude.php';

$db = Shop::Container()->getDB();
$gettext = Shop::Container()->getGetText();
$cache = Shop::Container()->getCache();
$alertService = Shop::Container()->getAlertService();

$wizardIO = new WizardIO($db, $cache, $alertService, $gettext);
$response = $wizardIO->answerQuestions(
  array (
    0 => 
    array (
      'name' => 'question-1',
      'value' => 'demoshopname',
    ),
    1 => 
    array (
      'name' => 'question-3',
      'value' => 'DE123456789',
    ),
    2 => 
    array (
      'name' => 'question-5[]',
      'value' => 'b2c',
    ),
    3 => 
    array (
      'name' => 'question-6',
      'value' => 'demoprefix',
    ),
    4 => 
    array (
      'name' => 'question-7',
      'value' => 'demosuffix',
    ),
    5 => 
    array (
      'name' => 'question-8',
      'value' => '0',
    ),
    6 => 
    array (
      'name' => 'question-11',
      'value' => 'email@email.com',
    ),
    7 => 
    array (
      'name' => 'question-12',
      'value' => 'email@email.com',
    ),
    8 => 
    array (
      'name' => 'question-13',
      'value' => '',
    ),
    9 => 
    array (
      'name' => 'question-14',
      'value' => 'email@email.com',
    ),
  )
);
