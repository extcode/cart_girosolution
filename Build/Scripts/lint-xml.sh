#!/usr/bin/env bash
#
# Validates the XLIFF files in Resources/Private/Language against XLIFF 1.2
# strict (Build/xliff-core-1.2-strict.xsd, imports Build/xml.xsd).
#
# xmllint is not installed via composer. It is provided by shell.nix, in CI it
# is installed via apt (libxml2-utils).
#
# Usage:
#   Build/Scripts/lint-xml.sh

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

if ! command -v xmllint > /dev/null; then
    echo "Missing xmllint, please install it first, e.g. the package libxml2-utils." >&2
    exit 1
fi

if [ -d Resources/Private/Language ]; then
    find Resources/Private/Language -name '*.xlf' -print0 \
        | xargs -0 -r xmllint --schema Build/xliff-core-1.2-strict.xsd --noout
fi
