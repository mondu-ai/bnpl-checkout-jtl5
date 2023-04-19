#!/bin/bash

helpFunction() {
    echo ""
    echo "Usage: $0 -v version"
    echo "\t-v Version of the plugin"
    exit 1 # Exit script after printing help
}

while getopts "v:o:c:" opt; do
    case "$opt" in
    v) version="$OPTARG" ;;
    o) old_version="$OPTARG" ;;
    c) changes="$OPTARG" ;;
    ?) helpFunction ;; # Print helpFunction in case parameter is non-existent
    esac
done

if ! echo $version | grep -Eq "^[0-9]+\.[0-9]+\.[0-9]+$"; then
    echo "Invalid version: ${version}"
    echo "Please specify a semantic version with no prefix (e.g. X.X.X)."
    exit 1
fi

if ! echo $old_version | grep -Eq "^[0-9]+\.[0-9]+\.[0-9]+$"; then
    echo "Invalid old version: ${old_version}"
    echo "Please specify a semantic version with no prefix (e.g. X.X.X)."
    exit 1
fi

if [[ $changes == "keep" ]]; then
    echo "Changes will be commited and pushed"
else
    echo "Changes won't be commited and pushed"
fi

OLD_TAG_SED_DOT="${old_version//./\\.}"
OLD_TAG_SED_DASH="${old_version//./\\-}"
NEW_TAG_SED_DOT="${version//./\\.}"
NEW_TAG_SED_DASH="${version//./\\-}"

files_to_update="info.xml"
files_to_rename="frontend/js/plugin.js frontend/css/style.css"

for file in $files_to_update; do
    echo "Updating semver occurrences in $file"
    sed -i "s/${OLD_TAG_SED_DOT}/${NEW_TAG_SED_DOT}/g" $file
    sed -i "s/${OLD_TAG_SED_DASH}/${NEW_TAG_SED_DASH}/g" $file
    if [[ $changes == "keep" ]]; then
        echo "Adding ${file} for new commit"
        git add $file
    fi
    sed -i "s/plugin\.js/plugin-${NEW_TAG_SED_DASH}\.js/g" $file
    sed -i "s/style\.css/style-${NEW_TAG_SED_DASH}\.css/g" $file
done

if [[ $changes == "keep" ]]; then
    echo "Pushing changes to default branch on remote"
    default_branch=$(git remote show origin | sed -n "/HEAD branch/s/.*: //p")

    git commit -m "Updated info.xml"
    git push origin "${default_branch}" -f
fi

for old_name in $files_to_rename; do
    echo "Renaming and adding semver to $old_name"
    new_name=$(sed "s/\./-$NEW_TAG_SED_DASH./" <<<$old_name)

    mv $old_name $new_name
done

mkdir MonduPayment
echo "Generating zip file"
rsync -r --exclude 'MonduPayment' --exclude '.git' --exclude '.github' --exclude 'releaser.sh' \
--exclude 'docker-compose.yml' --exclude 'activate.sh' --exclude 'shopscripts' --exclude 'docker' \
--exclude '.env.example' ./ MonduPayment
zip -r MonduPayment.zip MonduPayment

echo "Done"
