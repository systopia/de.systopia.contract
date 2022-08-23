#!/bin/sh
# regenerates a (dead) php file with all the strings in json custom data to be translated 

# generate temporary php file for the labels of the custom data structures
echo '<?php\n function l10n() {' > l10n.php
cat ../resources/*.json | grep '"title":' | sed 's/"title": /ts(/' | sed 's/",/");/' >> l10n.php
cat ../resources/*.json | grep '"label":' | sed 's/"label": /ts(/' | sed 's/",/");/' >> l10n.php
echo '}' >> l10n.php

