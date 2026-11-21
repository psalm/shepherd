[![Shepherd: Watch your PHP get stricter. Type coverage for PHP, by Psalm.](assets/img/og.png)](https://shepherd.dev)

# Psalm Shepherd server

![Psalm coverage](https://shepherd.dev/github/psalm/shepherd/coverage.svg)

## Tracking type coverage in public projects

Many public GitHub projects (Psalm included) display a small badge showing how much of the project’s code is covered by PHPUnit tests, because test coverage is a useful metric when trying to get an idea of a project’s code quality.

Hopefully, if you’ve got this far, you think type coverage is also important. To that end, I’ve created a service that allows you to display your project’s type coverage anywhere you want.

Psalm, PHPUnit and many other projects now display a type coverage badge in their READMEs.

You can generate your own by adding `--shepherd` to your CI Psalm command. Your badge will then be available at

`https://shepherd.dev/github/{username}/{repo}/coverage.svg`

The Psalm level badge is at `https://shepherd.dev/github/{username}/{repo}/level.svg`.

Both badges use the shields.io `flat` style. Add `?style=flat-square` for square corners.
For any other [shields.io style](https://shields.io/badges/endpoint-badge), point a shields.io endpoint badge at the JSON data of the badge:

`https://img.shields.io/endpoint?style=for-the-badge&url=https://shepherd.dev/github/{username}/{repo}/coverage`

Replace `coverage` with `level` at the end of the URL for the Psalm level.

This service is the beginning of an ongoing effort for Psalm to support open-source PHP projects hosted on GitHub.
