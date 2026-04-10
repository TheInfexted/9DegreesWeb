<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->set404Override(function () {
    return service('response')
        ->setStatusCode(404)
        ->setJSON(['message' => 'Route not found.']);
});

$routes->group('api/v1', function ($routes) {
    // Public
    $routes->post('auth/login',  'Api\AuthController::login');
    $routes->post('auth/logout', 'Api\AuthController::logout', ['filter' => 'jwt']);

    // Protected — all remaining routes
    $routes->group('', ['filter' => 'jwt'], function ($routes) {
        // Ambassadors
        $routes->get('ambassadors',              'Api\AmbassadorController::index');
        $routes->post('ambassadors',             'Api\AmbassadorController::create');
        $routes->get('ambassadors/(:num)',       'Api\AmbassadorController::show/$1');
        $routes->put('ambassadors/(:num)',       'Api\AmbassadorController::update/$1');
        $routes->delete('ambassadors/(:num)',    'Api\AmbassadorController::softDelete/$1');

        // Teams
        $routes->get('teams',                   'Api\TeamController::index');
        $routes->post('teams',                  'Api\TeamController::create');
        $routes->get('teams/(:num)',             'Api\TeamController::show/$1');
        $routes->put('teams/(:num)',             'Api\TeamController::update/$1');
        $routes->delete('teams/(:num)',          'Api\TeamController::delete/$1');
        $routes->put('teams/(:num)/leader',      'Api\TeamController::assignLeader/$1');

        // Roles
        $routes->get('roles',                   'Api\RoleController::index');
        $routes->post('roles',                  'Api\RoleController::create');
        $routes->put('roles/(:num)',             'Api\RoleController::update/$1');

        // Sales
        $routes->get('sales/months',            'Api\SaleController::months');
        $routes->get('sales/latest-defaults',   'Api\SaleController::latestDefaults');
        $routes->get('sales',                   'Api\SaleController::index');
        $routes->post('sales',                  'Api\SaleController::create');
        $routes->get('sales/(:num)',             'Api\SaleController::show/$1');
        $routes->put('sales/(:num)',             'Api\SaleController::update/$1');
        $routes->post('sales/(:num)/confirm',   'Api\SaleController::confirm/$1');
        $routes->post('sales/(:num)/void',      'Api\SaleController::void/$1');
        $routes->delete('sales/(:num)',          'Api\SaleController::delete/$1');

        // Commissions
        $routes->get('commissions/months',      'Api\CommissionController::months');
        $routes->get('commissions',             'Api\CommissionController::index');

        // Payouts
        $routes->get('payouts/months',                      'Api\PayoutController::months');
        $routes->get('payouts',                             'Api\PayoutController::index');
        $routes->post('payouts',                            'Api\PayoutController::create');
        $routes->post('payouts/batch',                      'Api\PayoutController::createBatch');
        $routes->get('payouts/(:num)',                      'Api\PayoutController::show/$1');
        $routes->delete('payouts/(:num)',                   'Api\PayoutController::delete/$1');
        $routes->post('payouts/(:num)/mark-paid',           'Api\PayoutController::markPaid/$1');
        $routes->post('payouts/(:num)/receipt',             'Api\PayoutController::uploadReceipt/$1');
        $routes->delete('payouts/(:num)/receipt/(:num)',    'Api\PayoutController::deleteReceipt/$1/$2');
        $routes->get('payouts/(:num)/summary',              'Api\PayoutController::downloadSummary/$1');
        $routes->post('payouts/(:num)/payslip',             'Api\PayoutController::generatePayslip/$1');
        $routes->get('payouts/(:num)/payslip',              'Api\PayoutController::downloadPayslip/$1');

        // Leaderboard
        $routes->get('leaderboard/months',      'Api\LeaderboardController::months');
        $routes->get('leaderboard',             'Api\LeaderboardController::index');

        // Access & Roles
        $routes->get('access',                  'Api\AccessController::index');
        $routes->post('access',                 'Api\AccessController::create');
        $routes->put('access/(:num)',            'Api\AccessController::update/$1');
        $routes->delete('access/(:num)',         'Api\AccessController::delete/$1');

        // Settings
        $routes->get('settings',                'Api\SettingsController::index');
        $routes->put('settings',                'Api\SettingsController::update');
        $routes->put('settings/password',       'Api\SettingsController::changePassword');
    });
});
