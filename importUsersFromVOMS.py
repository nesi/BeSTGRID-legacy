import VOMSAdmin
from VOMSAdmin import VOMSCommands
import re
import sys
import ConfigParser
import ldap
import ldap.filter
import MySQLdb

parser = ConfigParser.SafeConfigParser();
parser.read(sys.argv[1]);

vo = sys.argv[2]

#  group  id
defaultGid = parser.getint("User","group");

# connection to VOMS server
vomsConnection = VOMSCommands.VOMSAdminProxy(host=parser.get("VOMS","host"),
                                           port=parser.get("VOMS","port"),
                                           user_cert=parser.get("VOMS","certificate"),
                                           user_key=parser.get("VOMS","key"),
                                           vo=parser.get("VOMS","root_vo"));



# connection to ldap
ldap_uri = parser.get("LDAP","ldap_uri");
base_dn = parser.get("LDAP","base_dn");
user_base = parser.get("LDAP","user_base");
ldapConnection = ldap.initialize(ldap_uri);

maxUid = max([int(uid[1]['uidNumber'][0])
              for uid in ldapConnection.search_s(user_base,
                                                 ldap.SCOPE_SUBTREE,
                                                 "(&(!(uid=nfsnobody))(objectClass=posixAccount))",
                                                 ["uidNumber"])])
startUid = maxUid + 1;

# connection to mysql
dbConnection = MySQLdb.connect(host=parser.get("MySQL","host") ,
                               user=parser.get("MySQL","user"),
                               passwd=parser.get("MySQL","password"),
                               db=parser.get("MySQL","db"));

def get_shared_token(dn):
    return dn.split(" ")[-1]

# gets uid from shared token in db
def get_user_name(sharedToken):
    cursor = dbConnection.cursor();
    cursor.execute("SELECT uid FROM tb_st WHERE sharedToken='" + MySQLdb.escape_string(sharedToken) + "'");
    return cursor.fetchone()

def is_slcs_dn(dn):
    p = re.compile(".*/DC=slcs/.*")
    return (p.match(dn) != None)


# unix accounts can have up to 27 characters
def unix_account_name(cn):
    return ".".join(cn.split(" ")[0:2]).lower()[-27:]

def unix_display_name(cn):
    return " ".join(cn.split(" ")[0:-1])

class VOMSUser:
    def __init__(self,member,uid,gid):
        self.DN = member._DN
        self.CN = member._CN
        self.name = unix_account_name(self.CN);
        self.loginShell = parser.get("User","loginShell","/bin/bash");
        self.uid = uid
        self.gid = gid

    @property
    def homeDir(self):
        return  parser.get("User","home","/home") + "/" + self.name;

    def to_ldap(self,user_base):
        result = ""
        result = result + "dn: uid=%s,%s\n" % (self.name, user_base)
        result = result + "objectClass: posixAccount\n"
        result = result + "objectClass: shadowAccount\n"
        result = result + "objectClass: account\n"
        result = result + "cn: %s\n" % (self.name)
#        result = result + "displayName: %s\n" % (unix_display_name(self.CN))
        result = result + "gecos: %s\n" % (self.CN)
        result = result + "description: %s\n" % (self.DN)
#        result = result + "sn: %s\n" % (self.name)
        result = result + "uid: %s\n" % (self.name)
        result = result + "uidNumber: %s\n" % (self.uid)
        result = result + "gidNumber: %s\n" % (self.gid)
        result = result + "loginShell: %s\n" % (self.loginShell)
        result = result + "homeDirectory: %s\n" % (self.homeDir)
        return result


members = [m for m in vomsConnection.admin.listMembers(vo)];
uid = startUid
users = {}

for m in members:
    user = VOMSUser(m,uid,defaultGid);
    uid = uid + 1;
    # try to find user with same description
    print "# " + user.DN


    if (is_slcs_dn(user.DN)):
        user_name = get_user_name(get_shared_token(user.CN))
        if (user_name != None):
            user.name = user_name[0]

    ldapUser = ldapConnection.search_s(user_base,
                                       ldap.SCOPE_SUBTREE,
                                       "(description="+ldap.filter.escape_filter_chars(user.DN) + ")",
                                       ["uidNumber","gidNumber"]);
            
    if len(ldapUser):
        print "# Found %s" % user.DN
        # update without disturbing gid and uid
        #user.uid = ldapUser[0][1]["uidNumber"][0]
        #user.gid = ldapUser[0][1]["gidNumber"][0]
        uid = uid -1
        continue
    else:
        print "# Adding new user: %s" % user.name
        


    # try to find user with the same uid
    ldapUser = ldapConnection.search_s(user_base,
                                       ldap.SCOPE_SUBTREE,
                                       "(uid="+user.name + ")",
                                       ["description","uidNumber","gidNumber"]);
    if len(ldapUser):
        # ldap has user with the same username. lets see if description is set
        try:
            description = ldapUser[0][1]["description"][0]
            if (user.DN !=  description):
                user.name = user.name + "." + str(user.uid)
        except KeyError:
            # no description - we still need to preserve uid and gid
            user.uid = ldapUser[0][1]["uidNumber"][0]
            user.gid = ldapUser[0][1]["gidNumber"][0]
        
    if (users.has_key(user.name)):
        user.name = user.name + "." + str(user.uid)


    users[user.name] = user
    print(user.to_ldap(parser.get("LDAP","user_base")));
