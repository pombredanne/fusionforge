#!/bin/bash
do_config=$(grep ^do_config= /etc/sourceforge/sourceforge.conf | cut -d= -f2-)
if [ $# != 2 ]
then
	if [ $# = 0 ]
	then
		echo "Usage:"
		echo 	"$0 <domain_name> <ip_address>"
		echo 	"or"
		echo 	"$0 default"
		echo 	"$0 purge"
		exit 1
	else
		if [ "$1" = "default" ]
		then
			domain_name=$(hostname -f)
			ip_address=$(hostname -i)
			echo "Setting DNS with $domain_name $ip_address"
			$0 $domain_name $ip_address
		fi
		if [ "$1" = "purge" ]
		then
		    if [ "$do_config" = "true" ] ; then
        		if grep -q "// Next line inserted by Sourceforge install" /etc/bind/named.conf
        		then
                  		perl -pi -e "s:zone.*sourceforge.*};\n::" /etc/bind/named.conf
                  		perl -pi -e "s:// Next line inserted by Sourceforge install\n::" /etc/bind/named.conf
        		fi
		    fi
		fi
	fi
else
	export domain_name=$1
	export ip_address=$2
	echo "Configuring DNS for domain name = $domain_name and IP address = $ip_address..."
	if [ "$do_config" = "true" ] ; then
	    if ! grep -q "// Next line inserted by Sourceforge install" /etc/bind/named.conf 
		then
		cat >> /etc/bind/named.conf <<-EOF
// Next line inserted by Sourceforge install
zone "$domain_name" { type master; file "/var/lib/sourceforge/bind/dns.zone"; };
EOF
	    else
		echo "$ip_adress $domain_name"
  		#perl -pi -e "s:zone.*sourceforge.*};:zone \"$domain_name\" { type master; file \"/var/lib/sourceforge/bind/dns.zone\"; };:" /etc/bind/named.conf 
	    fi
	fi
	echo "Creating /var/lib/sourceforge/bind/dns.head"
	serial=`date '+%Y%m%d'`01
  	# cvs_host lists_host are useless for now
  	for i in domain_name ip_address serial ; do
  	    eval "sedexpr=\"$sedexpr|sed 's/{$i}/\${$i}/g'\""
 	done
  	eval "cat /var/lib/sourceforge/bind/dns.head.template $sedexpr > /var/lib/sourceforge/bind/dns.head"
  	if [ ! -f /var/lib/sourceforge/bind/dns.zone ]
  	then 
  		cp /var/lib/sourceforge/bind/dns.head /var/lib/sourceforge/bind/dns.zone
  	fi
  	chown sourceforge:sourceforge /var/lib/sourceforge/bind
  	chown sourceforge:sourceforge /var/lib/sourceforge/bind/dns.head
  	chown sourceforge:sourceforge /var/lib/sourceforge/bind/dns.zone
  	# echo "DNS Config is not complete:"
  	# echo "	-Does not do reverse, maybe not in the state of the art"
  	# echo "	-Suppose that all servers are in the same box"
  	# echo "	-Wizards advices are welcome"
	/usr/lib/sourceforge/bin/dns_conf.pl
	echo "DNS configuration done."
fi
