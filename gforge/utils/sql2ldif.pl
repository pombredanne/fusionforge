#!/usr/bin/perl
#
#  Convert SQL user database to LDIF format (for SourceForge LDAP schema)
#  by pfalcon@users.sourceforge.net 2000-10-17
#
#  ./sql2ldif.pl	: Dump only top-level ou map
#  ./sql2ldif.pl --full : Dump full database (ouch!)
#
#  $Id: sql2ldif.pl,v 1.8 2000/12/10 23:07:31 pfalcon Exp $
# 

use DBI;

#require("base64.pl");  # Include all the predefined functions
require("/usr/lib/sourceforge/lib/include.pl");  # Include all the predefined functions
$chroot="/var/lib/sourceforge/chroot";
&db_connect;

dump_header();

# if (!($#ARGV+1)) {
# 	exit;
# }

#
#  Dump user entries (ou=People)
#

# We give user maximum of privileges assigned to one by groups ;-(
my $query = "
SELECT user_name,realname,shell,unix_pw,unix_uid,MAX(cvs_flags),email
FROM users,user_group
WHERE unix_status='A'
      AND users.user_id=user_group.user_id
GROUP BY user_name,realname,shell,unix_pw,unix_uid,email
";
my $rel = $dbh->prepare($query);
$rel->execute();

#print "$sys_ldap_host\n";
#print "$sys_ldap_base_dn\n";

@cvs_flags2shell=('/dev/null','/bin/cvssh','/bin/bash');

while(my ($username, $realname, $shell, $pw, $uid, $cvs_flags, $email) = $rel->fetchrow()) {
	print "dn: uid=$username,ou=People,$sys_ldap_base_dn\n";
	#CB# To have the same id than generated by new_parse
	$uid += $uid_add;
	print "uid: $username\n";
	if (!$realname) { $realname='?'; }
	$realname=~tr#\x80-\xff#?#;  # it should be UTF-8 encoded, we just drop non-ascii chars
	print "cn: $realname\n";
	print "objectClass: account
objectClass: posixAccount
objectClass: top
objectClass: shadowAccount
objectClass: x-sourceforgeAccount
";
	#CB# gid was 100, i replace with $gid=$uid
	$gid = $uid;
	print "userPassword: {crypt}$pw
shadowLastChange: 10879
shadowMax: 99999
shadowWarning: 7
loginShell: $shell
x-cvsShell: $cvs_flags2shell[$cvs_flags]
uidNumber: $uid
gidNumber: $gid
homeDirectory: $chroot/home/users/$username
gecos: $realname
x-forward-email: $email

";
	#CB# To have the same id than generated by new_parse
	#CB# A group per user
	print "dn: cn=$username,ou=Group,$sys_ldap_base_dn
objectClass: posixGroup
objectClass: top
cn: $username
userPassword: {crypt}x
gidNumber: $gid

";
}

#
#  Dump group entries (ou=Group)
#

my $query = "
SELECT group_id,unix_group_name
FROM groups
WHERE status='A'
";
my $rel = $dbh->prepare($query);
$rel->execute();

while(my ($gid, $groupname) = $rel->fetchrow()) {
	my $query = "
SELECT user_name
FROM users,user_group
WHERE group_id=$gid
      AND users.user_id=user_group.user_id
";
	my $rel = $dbh->prepare($query);
	$rel->execute();

	#CB# To have the same id than generated by new_parse
	$gid += $gid_add;
	print "dn: cn=$groupname,ou=Group,$sys_ldap_base_dn
objectClass: posixGroup
objectClass: top
cn: $groupname
userPassword: {crypt}x
gidNumber: $gid
";

	while(my ($username) = $rel->fetchrow()) {
		print "memberUid: $username\n";
	}
	print "\n";
}

#
#  Dump mailing-lists entries (ou=mailingList)
#

$query = "SELECT mail_group_list.group_list_id,
                 mail_group_list.list_name,
                 users.user_name,
                 mail_group_list.password,
                 mail_group_list.description
          FROM mail_group_list, users
          WHERE mail_group_list.status = 3
                AND mail_group_list.list_admin = users.user_id" ;
$rel = $dbh->prepare($query);
$rel->execute();

while(my ($group_list_id, $listname, $user_name, $password, $description) = $rel->fetchrow()) {
	print "dn: cn=$listname,ou=mailingList,$sys_ldap_base_dn
objectClass: x-sourceforgeMailingList
objectClass: top
cn: $listname
listPostAddress: \"|/var/lib/mailman/mail/wrapper post $listname\"
listOwnerAddress: \"|/var/lib/mailman/mail/wrapper mailowner $listname\"
listRequestAddress: \"|/var/lib/mailman/mail/wrapper mailcmd $listname\"
";
	print "\n";
}

#
#  Dump CVS group entries (ou=cvsGroup)
#

my $query = "
SELECT group_id,unix_group_name
FROM groups
WHERE status='A'
";
my $rel = $dbh->prepare($query);
$rel->execute();

while(my ($gid, $groupname) = $rel->fetchrow()) {
	my $query = "
SELECT user_name
FROM users,user_group
WHERE group_id=$gid
      AND users.user_id=user_group.user_id
      AND user_group.cvs_flags > 0
";
	my $rel = $dbh->prepare($query);
	$rel->execute();

	#CB# To have the same id than generated by new_parse
	$gid += $gid_add;
	print "dn: cn=$groupname,ou=cvsGroup,$sys_ldap_base_dn
objectClass: posixGroup
objectClass: top
cn: $groupname
userPassword: {crypt}x
gidNumber: $gid
";

	while(my ($username) = $rel->fetchrow()) {
		print "memberUid: $username\n";
	}
	print "\n";
}

#
#  Auxilary functions
#

sub dump_header {
	print "dn: $sys_ldap_base_dn
dc: sourceforge
objectClass: top
objectClass: domain
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=Hosts,$sys_ldap_base_dn
ou: Hosts
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=People,$sys_ldap_base_dn
ou: People
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=Aliases,$sys_ldap_base_dn
ou: Aliases
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=Group,$sys_ldap_base_dn
ou: Group
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=cvsGroup,$sys_ldap_base_dn
ou: cvsGroup
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_default_domain

dn: ou=mailingList,$sys_ldap_base_dn
ou: mailingList
objectClass: top
objectClass: organizationalUnit
objectClass: domainRelatedObject
associatedDomain: $sys_lists_host

dn: uid=dummy,ou=People,$sys_ldap_base_dn
uid: dummy
cn: Dummy User
objectClass: account
objectClass: posixAccount
objectClass: top
objectClass: shadowAccount
objectClass: x-sourceforgeAccount
userPassword: {crypt}x
shadowLastChange: 10879
shadowMax: 99999
shadowWarning: 7
loginShell: /bin/false
x-cvsShell: /bin/false
uidNumber: $dummy_uid
gidNumber: $dummy_uid
homeDirectory: $chroot/home/users/dummy
gecos: Dummy User

";
}
