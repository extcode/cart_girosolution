{
  pkgs ? import <nixpkgs> { }
  ,phpVersion ? "php81"
}:

let
  php = pkgs.${phpVersion}.buildEnv {
    extensions = { enabled, all }: enabled ++ (with all; [
      xdebug
    ]);

    extraConfig = ''
      xdebug.mode = debug
      memory_limit = 4G
    '';
  };
  inherit(pkgs."${phpVersion}Packages") composer;

  projectInstall = pkgs.writeShellApplication {
    name = "project-install";
    runtimeInputs = [
      php
      composer
    ];
    text = ''
      rm -rf .Build/ vendor/ composer.lock
      composer update --prefer-dist --no-progress --working-dir="$PROJECT_ROOT"
    '';
  };

  projectCgl = pkgs.writeShellApplication {
    name = "project-cgl";

    runtimeInputs = [
      php
    ];

    text = ''
      ./vendor/bin/php-cs-fixer fix --config=Build/.php-cs-fixer.dist.php -v --dry-run --diff
    '';
  };

  projectCglFix = pkgs.writeShellApplication {
    name = "project-cgl-fix";

    runtimeInputs = [
      php
    ];

    text = ''
      ./vendor/bin/php-cs-fixer fix --config=Build/.php-cs-fixer.dist.php
    '';
  };

  projectLint = pkgs.writeShellApplication {
    name = "project-lint";

    runtimeInputs = [
      php
    ];

    text = ''
      find ./*.php Classes Configuration Tests -name '*.php' -print0 | xargs -0 -n 1 -P 4 php -l
    '';
  };

  projectPhpstan = pkgs.writeShellApplication {
    name = "project-phpstan";

    runtimeInputs = [
      php
    ];

    text = ''
      ./vendor/bin/phpstan analyse -c Build/phpstan.neon --memory-limit 256M
    '';
  };

in pkgs.mkShellNoCC {
  name = "TYPO3 Extension extcode/cart-products";
  buildInputs = [
    php
    composer
    projectInstall
    projectCgl
    projectCglFix
    projectLint
    projectPhpstan
  ];

  shellHook = ''
    export PROJECT_ROOT="$(pwd)"

    export typo3DatabaseDriver=pdo_sqlite
  '';
}