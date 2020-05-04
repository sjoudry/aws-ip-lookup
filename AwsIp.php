<?php

namespace AwsIp;

class Lookup
{

    /**
     * @var string $alias_target
     */
    private $alias_target;

    /**
     * @var string $aws
     */
    private $aws;

    /**
     * @var string $domain
     */
    private $domain;

    /**
     * @var string $elb_arn
     */
    private $elb_arn;

    /**
     * @var string $elb_domain
     */
    private $elb_domain;

    /**
     * @var string $elb_domain
     */
    private $elb_id;

    /**
     * @var string $elb_name
     */
    private $elb_name;

    /**
     * @var string $hosted_zone_id
     */
    private $hosted_zone_id;

    /**
     * @var string $listener_arn
     */
    private $listener_arn;

    /**
     * @var string $real_domain
     */
    private $real_domain;

    /**
     * @var string $region
     */
    private $region;

    /**
     * @var string $subdomain
     */
    private $subdomain;

    /**
     * @var string $target_group_arn
     */
    private $target_group_arn;

    /**
     * Constructor
     *
     * @param string $aws
     * @param string $domain
     * @param string $subdomain
     */
    public function __construct($aws, $domain, $subdomain = null)
    {
        $this->aws = $aws;
        $this->domain = $domain;
        $this->subdomain = $subdomain;

        // Build the real domain.
        $this->real_domain = $this->domain;
        if (isset($this->subdomain)) {
            $this->real_domain = $this->subdomain . '.' . $this->domain;
        }
    }

    /**
     * Run.
     *
     * Handles the entire lookup process.
     */
    public function run()
    {
        $start = microtime(true);
        $this->message('Looking up Web Server IP\'s for "%s":', [$this->real_domain]);

        $this->lookupHostedZone();
        $this->lookupARecord();
        $this->lookupElb();
        $this->parseElb();
        $this->lookupElbArn();
        $this->lookupElbHttpsListener();
        $this->lookupElbListenerRules();
        $this->lookupTargetGroupInstances();
        $this->lookupInstances();

        $end = microtime(true);
        $this->message('');
        $this->message('Request processed in %s seconds.', [$end - $start]);
    }

    /**
     * Error.
     *
     * Print the error message and stop execution.
     *
     * @param string $message
     * @param array $params
     */
    private function error($message, $params = [])
    {
        vprintf($message . PHP_EOL . PHP_EOL, $params);
        exit;
    }

    /**
     * Lookup A Record.
     *
     * Use the AWS CLI to lookup the A record for the hosted zone ID that was retrieved in lookupHostedZone().
     */
    private function lookupARecord()
    {

        /**
         * Sample output:
         *
         * [
         *   {
         *     "Name": "domain.com.",
         *     "Type": "A",
         *     "AliasTarget": {
         *       "HostedZoneId": "HostedZoneId",
         *       "DNSName": "DNSName.cloudfront.net.",
         *       "EvaluateTargetHealth": false
         *     }
         *   }
         * ]
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --hosted-zone-id %s --query "ResourceRecordSets[?Name == \'%s.\']"',
                [
                    $this->aws,
                    'route53',
                    'list-resource-record-sets',
                    $this->hosted_zone_id,
                    $this->real_domain,
                ]
            )
        );
        $response = json_decode($response);

        if (count($response) == 0) {
            $this->error('No A records found for "%s"', [$this->real_domain]);
        }

        $this->alias_target = $response[0]->AliasTarget->DNSName;

        $this->message('Looked up the A record for "%s":', [$this->real_domain], 1);
        $this->message('Alias Target: %s', [$this->alias_target], 2);
    }

    /**
     * Lookup ELB.
     *
     * Use the AWS CLI to lookup the ELB Domain for the alias target that was retrieved in lookupARecord().
     */
    private function lookupElb()
    {
        if (preg_match('/.*\.cloudfront\.net\.$/i', $this->alias_target) === 1) {
            /**
             * Sample output (truncated):
             *
             * [
             *   {
             *     "Id": "Id",
             *     "ARN": "ARN",
             *     "Status": "Deployed",
             *     "LastModifiedTime": "2020-05-01T14:33:55.296000+00:00",
             *     "DomainName": "DomainName.cloudfront.net",
             *     ...
             *     "Origins": {
             *       "Quantity": 1,
             *       "Items": [
             *         {
             *           "Id": "Id",
             *           "DomainName": "DomainName",
             *           "OriginPath": "",
             *           "CustomHeaders": {
             *             "Quantity": 0
             *           },
             *           "CustomOriginConfig": {
             *             "HTTPPort": 80,
             *             "HTTPSPort": 443,
             *             "OriginProtocolPolicy": "match-viewer",
             *             "OriginSslProtocols": {
             *             "Quantity": 3,
             *             "Items": [
             *               "TLSv1",
             *               "TLSv1.1",
             *               "TLSv1.2"
             *             ]
             *           },
             *           "OriginReadTimeout": 30,
             *           "OriginKeepaliveTimeout": 5
             *         }
             *       }
             *     ]
             *   },
             *   ...
             *   }
             * ]
             */
            $response = shell_exec(
                vsprintf(
                    '%s %s %s --query "DistributionList.Items[?DomainName == \'%s\']"',
                    [
                        $this->aws,
                        'cloudfront',
                        'list-distributions',
                        trim($this->alias_target, '.'),
                    ]
                )
            );
            $response = json_decode($response);

            if (count($response) == 0) {
                $this->error('No Cloudfront distribution found for "%s"', [$this->alias_target]);
            }

            $this->message('Cloudfront ID: %s', [$response[0]->Id], 2);
            $this->elb_domain = $response[0]->Origins->Items[0]->DomainName;
        } else {
            $this->elb_domain = $this->alias_target;
        }

        $this->message('Looked up the ELB domain for Alias Target "%s":', [$this->real_domain], 1);
        $this->message('ELB Domain: %s', [$this->elb_domain], 2);
    }

    /**
     * Lookup ELB ARN.
     *
     * Use the AWS CLI to lookup the ELB ARN for the ELB name that was retrieved in lookupElb().
     */
    private function lookupElbArn()
    {

        /**
         * Sample output:
         *
         * {
         *   "LoadBalancers": [
         *     {
         *       "LoadBalancerArn": "LoadBalancerArn",
         *       "DNSName": "DNSName",
         *       "CanonicalHostedZoneId": "CanonicalHostedZoneId",
         *       "CreatedTime": "2020-02-12T19:14:17.100000+00:00",
         *       "LoadBalancerName": "LoadBalancerName",
         *       "Scheme": "internet-facing",
         *       "VpcId": "VpcId",
         *       "State": {
         *         "Code": "active"
         *       },
         *       "Type": "application",
         *       "AvailabilityZones": [
         *         {
         *           "ZoneName": "ZoneName",
         *           "SubnetId": "SubnetId",
         *           "LoadBalancerAddresses": []
         *         },
         *         {
         *           "ZoneName": "ZoneName",
         *           "SubnetId": "SubnetId",
         *           "LoadBalancerAddresses": []
         *         }
         *       ],
         *       "SecurityGroups": [
         *         "SecurityGroup1",
         *         "SecurityGroup2"
         *       ],
         *       "IpAddressType": "ipv4"
         *     }
         *   ]
         * }
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --region %s --query "LoadBalancers[?DNSName == '\%s\']"',
                [
                    $this->aws,
                    'elbv2',
                    'describe-load-balancers',
                    $this->region,
                    $this->elb_name . '-' . $this->elb_id . '.' . $this->region . '.elb.amazonaws.com',
                ]
            )
        );
        $response = json_decode($response);

        $this->elb_arn = $response[0]->LoadBalancerArn;

        $this->message('Looked up the ELB ARN for ELB "%s":', [$this->elb_name], 1);
        $this->message('ELB ARN: %s', [$this->elb_arn], 2);
    }

    /**
     * Lookup ELB HTTPS Listener.
     *
     * Use the AWS CLI to lookup the Listener ARN for the ELB ARN that was retrieved in lookupElbArn().
     */
    private function lookupElbHttpsListener()
    {

        /**
         * Sample output (truncated):
         *
         * {
         *   "Listeners": [
         *     ...
         *     {
         *       "ListenerArn": "ListenerArn",
         *       "LoadBalancerArn": "LoadBalancerArn",
         *       "Port": 443,
         *       "Protocol": "HTTPS",
         *       "Certificates": [
         *         {
         *           "CertificateArn": "CertificateArn"
         *         }
         *       ],
         *       "SslPolicy": "ELBSecurityPolicy-2016-08",
         *       "DefaultActions": [
         *         {
         *           "Type": "forward",
         *           "TargetGroupArn": "TargetGroupArn",
         *           "ForwardConfig": {
         *             "TargetGroups": [
         *               {
         *                 "TargetGroupArn": "TargetGroupArn",
         *                 "Weight": 1
         *               }
         *             ],
         *             "TargetGroupStickinessConfig": {
         *               "Enabled": false
         *             }
         *           }
         *         }
         *       ]
         *     }
         *   ]
         * }
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --region %s --load-balancer-arn %s',
                [
                    $this->aws,
                    'elbv2',
                    'describe-listeners',
                    $this->region,
                    $this->elb_arn,
                ]
            )
        );
        $response = json_decode($response);

        if (count($response->Listeners) == 0) {
            $this->error('No listeners found for "%s"', [$this->elb_arn]);
        }

        $found = false;
        foreach ($response->Listeners as $listener) {
            if ($listener->Protocol == 'HTTPS') {
                $this->listener_arn = $listener->ListenerArn;
                $found = true;

                $this->message('Looked up the HTTPS Listener ARN for ELB "%s":', [$this->elb_name], 1);
                $this->message('Listener ARN: %s', [$this->listener_arn], 2);
                
                break;
            }
        }

        if (!$found) {
            $this->error('No listeners found for "%s"', [$this->elb_name]);
        }
    }

    /**
     * Lookup ELB Listener Rules.
     *
     * Use the AWS CLI to lookup the Target Group ARN for the Listener ARN that was retrieved in
     * lookupElbHttpsListener().
     */
    private function lookupElbListenerRules()
    {

        /**
         * Sample output (truncated):
         *
         * {
         *   "Rules": [
         *   ...
         *   {
         *     "RuleArn": "RuleArn",
         *     "Priority": "3",
         *     "Conditions": [
         *       {
         *         "Field": "host-header",
         *         "Values": [
         *           "domain.com"
         *         ],
         *         "HostHeaderConfig": {
         *           "Values": [
         *             "domain.com"
         *           ]
         *         }
         *       }
         *     ],
         *     "Actions": [
         *       {
         *         "Type": "forward",
         *         "TargetGroupArn": "TargetGroupArn",
         *         "Order": 1,
         *         "ForwardConfig": {
         *         "TargetGroups": [
         *           {
         *             "TargetGroupArn": "TargetGroupArn",
         *             "Weight": 1
         *           }
         *         ],
         *         "TargetGroupStickinessConfig": {
         *           "Enabled": false
         *         }
         *       }
         *     }
         *   ],
         *   "IsDefault": false
         * },
         * ...
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --region %s --listener-arn %s',
                [
                    $this->aws,
                    'elbv2',
                    'describe-rules',
                    $this->region,
                    $this->listener_arn,
                ]
            )
        );
        $response = json_decode($response);

        if (count($response->Rules) == 0) {
            $this->error('No rules found for "%s"', [$this->listener_arn]);
        }

        // Lookup host-header rule.
        $found = false;
        foreach ($response->Rules as $rule) {
            if (count($rule->Conditions)) {
                foreach ($rule->Conditions as $condition) {
                    if ($condition->Field == 'host-header') {
                        foreach ($condition->Values as $value) {
                            if ($value == $this->real_domain) {
                                $this->target_group_arn = $rule->Actions[0]->TargetGroupArn;
                                $found = true;
                                break 3;
                            }
                        }
                    }
                }
            }
        }

        // Lookup default rule.
        if (!$found) {
            foreach ($response->Rules as $rule) {
                if ($rule->IsDefault == 1) {
                    $this->target_group_arn = $rule->Actions[0]->TargetGroupArn;
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            $this->message('Looked up the Target Group ARN for Listener "%s":', [$this->listener_arn], 1);
            $this->message('Target Group ARN: %s', [$this->target_group_arn], 2);
        } else {
            $this->error('No target groups found for "%s"', [$this->listener_arn]);
        }
    }

    /**
     * Lookup Hosted Zone.
     *
     * Use the AWS CLI to lookup the hosted zone ID from route 53 for the specified domain.
     */
    private function lookupHostedZone()
    {

        /**
         * Sample output
         *
         * {
         *   "HostedZones": [
         *     {
         *       "Id": "/hostedzone/ID",
         *       "Name": "domain.com.",
         *       "CallerReference": "CallerReference",
         *       "Config": {
         *         "PrivateZone": false
         *       },
         *       "ResourceRecordSetCount": 7
         *     }
         *   ],
         *   "DNSName": "domain.com",
         *   "IsTruncated": true,
         *   "NextDNSName": "nextdomain.com.",
         *   "NextHostedZoneId": "NextHostedZoneId",
         *   "MaxItems": "1"
         * }
        */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --dns-name %s --max-items 1',
                [
                    $this->aws,
                    'route53',
                    'list-hosted-zones-by-name',
                    $this->domain,
                ]
            )
        );
        $response = json_decode($response);

        if (!isset($response->HostedZones[0]->Name) ||
            (isset($response->HostedZones[0]->Name) && $response->HostedZones[0]->Name !== $this->domain . '.')) {
            $this->error('Error: Domain name not found!');
        }

        $hosted_zone = explode('/', trim($response->HostedZones[0]->Id));
        $this->hosted_zone_id = array_pop($hosted_zone);

        $this->message('Looked up the Hosted Zone ID for Domain "%s":', [$this->domain], 1);
        $this->message('Hosted Zone ID: %s', [$this->hosted_zone_id], 2);
    }

    /**
     * Lookup Target Group Instances.
     *
     * Use the AWS CLI to lookup the target group instance IDs that were retrieved from lookupElbListenerRules().
     */
    private function lookupInstances()
    {

        /** Sample output (truncated):
         * {
         *   "Reservations": [
         *     {
         *       "Groups": [],
         *       "Instances": [
         *         {
         *           "AmiLaunchIndex": 0,
         *           "ImageId": "ImageId",
         *           "InstanceId": "InstanceId",
         *           "InstanceType": "t3.xlarge",
         *           "KeyName": "KeyName",
         *           ...
         *           "PrivateIpAddress": "999.999.999.999",
         *           ...
         *           "Tags": [
         *             {
         *               "Key": "Key",
         *               "Value": "owned"
         *             },
         *             {
         *               "Key": "Name",
         *               "Value": "Value"
         *             }
         *           ],
         *           ...
         *         }
         *       ],
         *       "OwnerId": "OwnerId",
         *       "ReservationId": "ReservationId"
         *     }
         *   ]
         * }
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --region %s --instance-ids %s',
                [
                    $this->aws,
                    'ec2',
                    'describe-instances',
                    $this->region,
                    implode(' ', array_keys($this->instance_ids)),
                ]
            )
        );
        $response = json_decode($response);

        $server_ips = [];
        foreach ($response->Reservations as $instance) {
            $server_name = '';
            foreach ($instance->Instances[0]->Tags as $tag) {
                if ($tag->Key == 'Name') {
                    $server_name = $tag->Value;
                }
            }
            $server_ips[$server_name] = $instance->Instances[0]->PrivateIpAddress;
        }
        ksort($server_ips);

        $this->message('Looked up the Instance IP\'s:', [], 1);
        foreach ($server_ips as $name => $ip) {
            $this->message('%s = %s', [$ip, $name], 2);
        }
    }

    /**
     * Lookup Target Group Instances.
     *
     * Use the AWS CLI to lookup the target group instance IDs that were retrieved from lookupElbListenerRules().
     */
    private function lookupTargetGroupInstances()
    {

        /**
         * Sample output:
         *
         * {
         *   "TargetHealthDescriptions": [
         *     {
         *       "Target": {
         *         "Id": "Id",
         *         "Port": 80
         *       },
         *       "HealthCheckPort": "80",
         *       "TargetHealth": {
         *         "State": "healthy"
         *       }
         *     },
         *   ]
         * }
         */
        $response = shell_exec(
            vsprintf(
                '%s %s %s --region %s --target-group-arn %s',
                [
                    $this->aws,
                    'elbv2',
                    'describe-target-health',
                    $this->region,
                    $this->target_group_arn,
                ]
            )
        );
        $response = json_decode($response);

        $this->instance_ids = [];
        foreach ($response->TargetHealthDescriptions as $instance) {
            $this->instance_ids[$instance->Target->Id] = $instance->Target->Port;
        }

        $this->message('Looked up the Instance Ids in the Target Group "%s":', [$this->target_group_arn], 1);
        foreach ($this->instance_ids as $id => $port) {
            $this->message('Instance ID: %s (Port %s)', [$id, $port], 2);
        }
    }

    /**
     * Message.
     *
     * Print an info message to the console.
     *
     * @param string $message
     * @param array $params
     * @param int $tabs
     */
    private function message($message, $params = [], $tabs = 0)
    {
        for ($i = 0; $i < $tabs; $i++) {
            print "\t";
        }
        vprintf($message . PHP_EOL, $params);
    }

    /**
     * Parse ELB Domain.
     *
     * Parse the ELB Domain that was retrieved by lookupElb().
     */
    private function parseElb()
    {
        preg_match('/([^\.]*)-([\d]*)\.([^\.]*)\.elb\.amazonaws\.com\.?$/i', $this->elb_domain, $matches);
        $this->elb_name = $matches[1];
        $this->elb_id = $matches[2];
        $this->region = $matches[3];
    }
}
