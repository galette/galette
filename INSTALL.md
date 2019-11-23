Pick one of URL from [Galette repository](http://doc.galette.eu/en/develop/development/git.html), and clone the repository.

Assuming you are in the root directory of your working copy.

Optionally, enable git flow:
```
$ git flow init
```

Install PHP dependencies:
```
$ cd galette
$ composer install
$ cd -
```

Install FomanticUI:
```
$ cd semantic
$ npx gulp build
$ cd -
```

Install assets:
```
$ npx gulp build
```

You're done! Enjoy Galette :)
