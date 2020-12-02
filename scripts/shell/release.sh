#!/bin/bash

mkdir release

pwdbak=$(pwd)

ls -1 extensions | while read extension; do
	echo "Create \"${extension}\" zip..."
	cd extensions/$extension/$extension
	sed -i "s/<\/server>/https:\/\/${HOSTUSER}.github.io\/${extension}\/updates.xml<\/server>/" ${extension}.xml
	zip -r ../../../release/${extension} *
	cd ${pwdbak}
done

exit 0
