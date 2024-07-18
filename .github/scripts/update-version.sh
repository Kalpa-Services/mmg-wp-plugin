#!/bin/bash

# Get the new version number from the tag
NEW_VERSION=$(echo $GITHUB_REF | cut -d / -f 3)

# Remove the 'v' prefix if present
NEW_VERSION=${NEW_VERSION#v}

# Update the version in main.php
sed -i "s/Version: [0-9.]\+/Version: $NEW_VERSION/" main.php

# Commit the change
git config --local user.email "action@github.com"
git config --local user.name "GitHub Action"
git add main.php
git commit -m "Bump version to $NEW_VERSION"

# Push the change
git push