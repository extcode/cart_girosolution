{
  pkgs ? import <nixpkgs> { }
  ,phpVersion ? "php82"
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
      rm -rf .Build/ composer.lock
      composer update --prefer-dist --no-progress --working-dir="$PROJECT_ROOT"
    '';
  };

  projectCgl = pkgs.writeShellApplication {
    name = "project-cgl";

    runtimeInputs = [
      php
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/cgl.sh"
    '';
  };

  projectCglFix = pkgs.writeShellApplication {
    name = "project-cgl-fix";

    runtimeInputs = [
      php
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/cgl.sh" --fix
    '';
  };

  projectLintPhp = pkgs.writeShellApplication {
    name = "project-lint-php";

    runtimeInputs = [
      php
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/lint-php.sh"
    '';
  };

  projectLintTypoScript = pkgs.writeShellApplication {
    name = "project-lint-typoscript";

    runtimeInputs = [
      php
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/lint-typoscript.sh"
    '';
  };

  projectLintXml = pkgs.writeShellApplication {
    name = "project-lint-xml";

    runtimeInputs = [
      pkgs.libxml2
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/lint-xml.sh"
    '';
  };

  projectLintYaml = pkgs.writeShellApplication {
    name = "project-lint-yaml";

    runtimeInputs = [
      pkgs.yamllint
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/lint-yaml.sh"
    '';
  };

  projectLint = pkgs.writeShellApplication {
    name = "project-lint";

    runtimeInputs = [
      projectLintPhp
      projectLintTypoScript
      projectLintXml
      projectLintYaml
    ];

    text = ''
      project-lint-php
      project-lint-typoscript
      project-lint-xml
      project-lint-yaml
    '';
  };

  projectPhpstan = pkgs.writeShellApplication {
    name = "project-phpstan";

    runtimeInputs = [
      php
    ];

    text = ''
      "$PROJECT_ROOT/Build/Scripts/phpstan.sh"
    '';
  };

in pkgs.mkShellNoCC {
  name = "TYPO3 Extension extcode/cart-girosolution";
  buildInputs = [
    pkgs.cacert
    pkgs.libxml2
    pkgs.yamllint
    php
    composer
    projectInstall
    projectCgl
    projectCglFix
    projectLint
    projectLintPhp
    projectLintTypoScript
    projectLintXml
    projectLintYaml
    projectPhpstan
  ];

  shellHook = ''
    export PROJECT_ROOT="$(pwd)"
  '';
}
