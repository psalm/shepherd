# Psalm Shepherd server

![Psalm coverage](https://shepherd.dev/github/psalm/shepherd/coverage.svg)

## Tracking type coverage in public projects

Many public GitHub projects (Psalm included) display a small badge showing how much of the project’s code is covered by PHPUnit tests, because test coverage is a useful metric when trying to get an idea of a project’s code quality.

Hopefully, if you’ve got this far, you think type coverage is also important. To that end, I’ve created a service that allows you to display your project’s type coverage anywhere you want.

Psalm, PHPUnit and many other projects now display a type coverage badge in their READMEs.

You can generate your own by adding `--shepherd` to your CI Psalm command. Your badge will then be available at

`https://shepherd.dev/github/{username}/{repo}/coverage.svg`
This service is the beginning of an ongoing effort for Psalm to support open-source PHP projects hosted on GitHub.
