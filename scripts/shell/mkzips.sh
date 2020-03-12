#!/bin/bash

mkdir zips

pwdbak=$(pwd)

ls -1 extensions | while read extension; do
	cd extensions/$extension/$extension
	zip -r ../../../zips/${extension} *
	cd ${pwdbak}
done

exit 0
