<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a real image upload without GD.
 */
function mediaUpload(string $name = 'photo.png', ?string $fixture = null): UploadedFile
{
    $fixture ??= str_ends_with(strtolower($name), '.jpg') || str_ends_with(strtolower($name), '.jpeg')
        ? 'pixel.jpg'
        : 'pixel.png';

    $path = __DIR__.'/Fixtures/images/'.$fixture;

    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
}

/**
 * Read the raw bytes of an image fixture.
 */
function mediaFixture(string $fixture = 'pixel.png'): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/images/'.$fixture);
}
