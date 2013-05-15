
# Use STRICT ACL policy, exception for QuickShare (allow QuickShare to see all directories)
acAclPolicy {ON($userNameClient == "QuickShare") { } }
acAclPolicy {msiAclPolicy("STRICT"); }

# Invoke createUser script when no GSI DN mapping exists 
# This script either creates a mapping to an existing account (as identified by
# SharedToken) or creates a new user account
acGetUserByDN(*arg,*OUT) {msiExecCmd("createUser","*arg","null","null","null",*OUT); }

# Set default resource for creating new data objects and reading existing data objects
acSetRescSchemeForCreate {msiSetDefaultResc("DEFAULT_RESOURCE","null"); }
acPreprocForDataObjOpen {msiSetDataObjPreferredResc("DEFAULT_RESOURCE"); }

# Store files in Vault with path including all elements of the logical path
# included (trimCnt=1) and no username pre-pended:
acSetVaultPathPolicy {msiSetGraftPathScheme("no","0"); }

# Allow the use of Trash (default) (not calling msiNoTrashCan):
acTrashPolicy { }

# Allow up to 4 RE processes
acSetReServerNumProc {msiSetReServerNumProc("4"); }

