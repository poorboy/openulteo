#! /bin/sh -e

unset LANG

debconf-set-selections << EOF
sun-java6-bin	shared/accepted-sun-dlj-v1-1	boolean	true
sun-java6-jre	shared/accepted-sun-dlj-v1-1	boolean	true
EOF
