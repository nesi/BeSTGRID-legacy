#
# The way rules are applied by the rule engine is such that, if the execution of a rule fails, 
# the rule engine looks for the next rule with the same name in the configuration, and executes it.
# This process is repeated until one rule succeeds, or execution of all rules with the same name 
# has been attemted.
# This means that, in order to prevent execution of the default rules in 'core.re' in case one of 
# the rules in this file fails, it is necessary to comment out rules with names matching the 
# rules in this file in 'core.re'.
#

# Use STRICT ACL policy, exception for QuickShare (allow QuickShare to see all directories)
acAclPolicy {ON($userNameClient == "QuickShare") { } }
# As of iRODS 3.3.1, rules processing does not stop on the first rule with
# matching pre-condition, so we must explicitly specify non-match for the other
# alternative
acAclPolicy {ON($userNameClient != "QuickShare") { msiAclPolicy("STRICT"); } }
##acAclPolicy {msiAclPolicy("STRICT"); }

# Invoke createUser script when no GSI DN mapping exists 
# This script either creates a mapping to an existing account (as identified by
# SharedToken) or creates a new user account
acGetUserByDN(*arg,*OUT) {msiExecCmd("createUser","*arg","null","null","null",*OUT); }

# Set default resource for creating new data objects and reading existing data objects
#
# Michael Keller, 17/07/2013:
# The 'forced' in the following stanzas can be used to force users to access
# one specific resource. This can be used to isolate another resource for
# maintenance.
#
#acSetRescSchemeForCreate {msiSetDefaultResc("DEFAULT_RESOURCE","forced"); }
#acSetRescSchemeForRepl {msiSetDefaultResc("DEFAULT_RESOURCE","forced"); }
acSetRescSchemeForCreate {msiSetDefaultResc("DEFAULT_RESOURCE","null"); }
acSetRescSchemeForRepl {msiSetDefaultResc("DEFAULT_RESOURCE_GROUP","null"); }
acPreprocForDataObjOpen {msiSetDataObjPreferredResc("DEFAULT_RESOURCE"); }

# Store files in Vault with path including all elements of the logical path
# included (trimCnt=1) and no username pre-pended:
acSetVaultPathPolicy {msiSetGraftPathScheme("no","0"); }

# Allow the use of Trash (default) (not calling msiNoTrashCan):
#
# Michael Keller, 17/07/2013:
# This is now the default.
#
#acTrashPolicy { }

# Allow up to 4 RE processes
acSetReServerNumProc {msiSetReServerNumProc("4"); }

