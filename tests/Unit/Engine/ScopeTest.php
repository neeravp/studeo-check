<?php

use Studeo\Check\Engine\Scope;

it('defines ALL as dot', function () {
    expect(Scope::ALL)->toBe(['.'])
        ->and(Scope::all()->patterns)->toBe(['.']);
});

it('defines APP_CODE matching the eleven CAP 1 directories in exact order', function () {
    $expected = ['Actions', 'Commands', 'Concerns', 'Controllers', 'Data', 'Jobs', 'Listeners', 'Livewire', 'Middleware', 'Policies', 'Services'];

    expect(Scope::APP_CODE)->toBe($expected)
        ->and(Scope::appCode()->patterns)->toBe($expected);
});

it('matches directories by prefix', function () {
    $scope = Scope::appCode();

    expect($scope->matches('Actions/CreateOrderAction.php'))->toBeTrue()
        ->and($scope->matches('Actions/Sub/NestedAction.php'))->toBeTrue()
        ->and($scope->matches('Services/PaymentGateway.php'))->toBeTrue()
        ->and($scope->matches('Models/Order.php'))->toBeFalse()
        ->and($scope->matches('database/migrations/create_posts.php'))->toBeFalse();
});

it('matches any relative path when pattern is dot', function () {
    $scope = Scope::all();

    expect($scope->matches('Actions/Run.php'))->toBeTrue()
        ->and($scope->matches('Models/Post.php'))->toBeTrue()
        ->and($scope->matches('root.php'))->toBeTrue();
});

it('matches glob patterns using fnmatch', function () {
    $scope = new Scope(['*Action.php', 'Models/?ost.php', 'Data/[UV]*.php']);

    expect($scope->matches('Actions/CreateOrderAction.php'))->toBeTrue()
        ->and($scope->matches('RunAction.php'))->toBeTrue()
        ->and($scope->matches('Models/Post.php'))->toBeTrue()
        ->and($scope->matches('Models/Cost.php'))->toBeTrue()
        ->and($scope->matches('Models/User.php'))->toBeFalse()
        ->and($scope->matches('Data/UserData.php'))->toBeTrue()
        ->and($scope->matches('Data/userData.php'))->toBeFalse()
        ->and($scope->matches('Other/File.php'))->toBeFalse();
});
