# AWS IP Lookup

Lookup AWS web server IP addresses. This tool assumes that the following architecture is in place:

Route 53 -> Cloudfront -> ELB -> EC2

Or

Route 53 -> ELB -> EC2

## Requirements

This tool requires BASH, PHP and the AWS CLI.

### Check out the Repo

Clone this repo somewhere on your machine.

### Install AWS CLI

This tool is compatible with version 1 and 2 of the CLI.

https://docs.aws.amazon.com/cli/latest/userguide/cli-chap-install.html

### Configure AWS CLI

Before you configure the AWS CLI, you will need access to our AWS account and create a set of API keys.

#### Create Keys

https://console.aws.amazon.com/iam/home?region=ca-central-1#/security_credentials

#### Configure CLI

https://docs.aws.amazon.com/cli/latest/userguide/cli-chap-configure.html#cli-quick-configuration

```bash
$ aws configure
AWS Access Key ID [None]: [ACCESS_KEY_ID_GENERATED_IN_AWS]
AWS Secret Access Key [None]: [SECRET_ACCESS_KEY_GENERATED_IN_AWS]
Default region name [None]: ca-central-1
Default output format [None]: json
```

**Important**: Make sure you set `json` as the output format.

## Create an alias

This assumes you are using the .bashrc file. If your OS has another way to manage aliases, use that instead.

```bash
vi ~/.bashrc
```

Add the following to your .bashrc file. The path may be different on your machine.

```bash
alias awsip='/path/to/aws-ip-lookup/lookup.sh'
```

Import the changes into your shell environment:

```bash
source ~/.bashrc
```

## Examples

### Lookup Prod Server IP's

```bash
$ awsip -d=domain.com
Looking up Web Server IP's for "domain.com":
	Looked up the Hosted Zone ID for Domain "domain.com":
		Hosted Zone ID: ID
	Looked up the A record for "domain.com":
		Alias Target: domain.cloudfront.net.
		Cloudfront ID: ID
	Looked up the ELB domain for Alias Target "domain.com":
		ELB Domain: ELB.us-east-1.elb.amazonaws.com
	Looked up the ELB ARN for ELB "ELB":
		ELB ARN: ARN
	Looked up the HTTPS Listener ARN for ELB "ELB":
		Listener ARN: ARN
	Looked up the Target Group ARN for Listener "ARN":
		Target Group ARN: ARN
	Looked up the Instance Ids in the Target Group "ARN":
		Instance ID: ID (Port 80)
		Instance ID: ID (Port 80)
	Looked up the Instance IP's:
		999.999.999.998 = NAME
		999.999.999.999 = NAME

Request processed in 11.40115404129 seconds.

```

### Lookup Sub-Domain Server IP's

```bash
$ awsip -d=domain.com -s=sub
Looking up Web Server IP's for "sub.domain.com":
	Looked up the Hosted Zone ID for Domain "domain.com":
		Hosted Zone ID: ID
	Looked up the A record for "sub.domain.com":
		Alias Target: domain.cloudfront.net.
		Cloudfront ID: ID
	Looked up the ELB domain for Alias Target "sub.domain.com":
		ELB Domain: ELB.us-east-1.elb.amazonaws.com
	Looked up the ELB ARN for ELB "ELB":
		ELB ARN: ARN
	Looked up the HTTPS Listener ARN for ELB "ELB":
		Listener ARN: ARN
	Looked up the Target Group ARN for Listener "ARN":
		Target Group ARN: ARN
	Looked up the Instance Ids in the Target Group "ARN":
		Instance ID: ID (Port 80)
		Instance ID: ID (Port 80)
	Looked up the Instance IP's:
		999.999.999.998 = NAME
		999.999.999.999 = NAME

Request processed in 12.282615184784 seconds.
```

## Help

```bash
$ ./lookup.sh
Usage: ./lookup.sh -d=DOMAIN [-s=SUBDOMAIN]

-d --domain      The domain used to lookup the hosted zone file.
-s --sub-domain  The sub-domain used to lookup the A record of the domain.
```
