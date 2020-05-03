#!/bin/bash
#
# AWS IP Lookup
#
# This is a wrapper script for the lookup.php file that is
# bundled with this file.
#
# Author: Scott Joudry <sj@slydevil.com>
# Created: May 22, 2019
###########################################################

# Determine if aws-shell is installed.
AWS="$(which aws)"
if [ -z "$AWS" ]; then
  echo "aws-shell is not installed and is required. See https://aws.amazon.com/cli/ for installation instructions."
  exit
fi

# Determine if PHP is installed.
PHP="$(which php)"
if [ -z "$PHP" ]; then
  echo "PHP is not installed and is required. See https://www.php.net/manual/en/install.php for installation instructions."
  exit
fi

# Handle arguments.
for ARG in "$@"
do
  case $ARG in
    -d=*|--domain=*)
    DOMAIN="${ARG#*=}"
    shift
    ;;
    -s=*|--sub-domain=*)
    SUBDOMAIN="${ARG#*=}"
    shift
    ;;
  esac
done

# Determine if args are sufficient.
HELP=0
if [ -z "$DOMAIN" ]; then
  HELP=1
fi

if [ "$HELP" = 1 ]; then
  echo "Usage: ./lookup.sh -d=DOMAIN [-s=SUBDOMAIN]"
  echo
  echo "-d --domain      The domain used to lookup the hosted zone file."
  echo "-s --sub-domain  The sub-domain used to lookup the A record of the domain."
  exit
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
$DIR/lookup.php $AWS $DOMAIN $SUBDOMAIN
