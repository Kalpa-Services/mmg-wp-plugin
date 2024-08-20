#!/bin/bash

# Get the new version number from the tag
NEW_VERSION=$(echo $GITHUB_REF | cut -d / -f 3)

# Remove the 'v' prefix if present
NEW_VERSION=${NEW_VERSION#v}

# Update the version in mmg-checkout-payment.php (both in the comment block and in the body)
sed -i "s/\([ *]*Version:[ ]*\)[0-9.]\+/\1$NEW_VERSION/" mmg-checkout-payment.php
sed -i "s/\(define('MMG_PLUGIN_VERSION', '\)[0-9.]\+/\1$NEW_VERSION/" mmg-checkout-payment.php
# Update the Stable tag in README.txt
sed -i "s/\(Stable tag: \)[0-9.]\+/\1$NEW_VERSION/" README.txt

# Add README.txt to the commit
git add README.txt

# Commit the change
git add mmg-checkout-payment.php
git commit -m "Bump version to $NEW_VERSION"

# Push the change to the main branch
git push origin HEAD:main