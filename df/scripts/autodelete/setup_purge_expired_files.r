setupPurgeExpiredFiles {
  if (*test == 0) {
    *delay = "<ET>00:00:00</ET><EF>1h</EF>";
  } else {
    *delay = "<EF>1m</EF>";
  }

  delay(*delay) {
    msiGetIcatTime(*Time, "unix");
    msiGetIcatTime(*TimeStamp, "");
    msiMakeQuery("DATA_NAME, COLL_NAME","DATA_EXPIRY < '*Time' and DATA_EXPIRY != '' and COLL_NAME not like '/%/trash/%'", *Query);
    msiExecStrCondQuery(*Query, *List);
    foreach(*List) {
      msiGetValByKey(*List, "DATA_NAME", *D);
      msiGetValByKey(*List, "COLL_NAME", *E);
      msiSetACL("default", "admin:own", "rods", "*E/*D");
      msiDataObjUnlink("objPath=*E/*D++++forceFlag=", *Status);
      writeLine("serverLog", "Purged File *E/*D at *TimeStamp");
    }
  }
}
INPUT *test=0
OUTPUT ruleExecOut

