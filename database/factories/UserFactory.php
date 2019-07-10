<?php

$factory->define(UniqKey\Laravel\SCIMServer\Tests\Model\User::class, function (Faker\Generator $faker) {
    return [
        // 'username' => $faker->userName,
        'email' => $faker->email,
        'name' => $faker->name,
        'password' => 'test',
    ];
});
