#include <stdio.h>
#include <stdlib.h>
#include <math.h>
#include <string>
#include <arpa/inet.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <netdb.h>
#include "geoip.h"
#include <string.h>
#include "AdvXMLParser.h"


using namespace std;
using namespace AdvXMLParser;

//convert degrees to radians, needed for distance calculation
static double torad(double deg)
{
    return deg*M_PI/180.0;     
}

//Resolve name into IPv4 address
static unsigned long resolve(const char *name)
{
    if(!name)
         return 0;
    unsigned long ip=inet_addr(name);

    if(ip!=INADDR_NONE)
        return ip;
    struct hostent *e=gethostbyname(name);
    if(!e)
       return 0;
    struct in_addr ad=*(struct in_addr *)*e->h_addr_list;
    return ad.s_addr;
}

//Stringify IP address
static string tostring(unsigned long ip)
{
    struct in_addr addr;

    addr.s_addr=ip;
    string str=inet_ntoa(addr);
    return str;
}

GeoIP::GeoIP():m_bCoordSet(false)
{
}

//IPINFODB api key
const char *API_KEY="b7ba88a2673f29771f5d844dcb5de6136ac923441a9b31bb76c4d4bbd2f07056";

string GeoIP::request(unsigned long ip)
{
    struct sockaddr_in addr;
    int sock;
    string ret;

    memset(&addr,0,sizeof(addr));
    //addr.sin_addr.s_addr=resolve("freegeoip.net");
    addr.sin_addr.s_addr=resolve("api.ipinfodb.com");
    addr.sin_port=htons(80);
    addr.sin_family=AF_INET;
    sock=socket(AF_INET,SOCK_STREAM,0);
    //connect and request information
    if(!connect(sock,(struct sockaddr *)&addr,sizeof(addr)))
    {
        char buf[2048];
        int i;
 
        //sprintf(buf,"GET /xml/%s HTTP/1.0\r\nHost: freegeoip.net\r\n\r\n",(ip?tostring(ip).c_str():""));
        sprintf(buf,"GET /v2/ip_query.php?key=%s&ip=%s&timezone=false  HTTP/1.0\nHost: api.ipinfodb.com\nConnection: close\n\n",API_KEY,(ip?tostring(ip).c_str():""));
        send(sock,buf,strlen(buf),0);
        //wait for full reply
        while((i=recv(sock,buf,2000,0))>0)
        {
            buf[i]=0;
            ret+=buf;
        }
        int pos=ret.find("\r\n\r\n");
        if(pos==-1)
        {
           pos=ret.find("\n\n");
           if(pos!=-1)
               ret=ret.substr(pos+2);
        }
        else
            ret=ret.substr(pos+4);
    }
    close(sock);
    return ret;
}

GeoIP::~GeoIP()
{
}

std::pair<double,double> GeoIP::coord(unsigned long ip)
{
    string str=request(ip);
    pair<double,double> d;
    Parser parser;


    d.first=0;
    d.second=0;
    if(!str.size())
        return d; //nothing received, return 0,0
    //parse XML
    auto_ptr<Document> pDoc(parser.Parse(str.c_str(),str.size()));
    const Element& root=pDoc->GetRoot();
    //obtain coordinate values
    string lon=root("Longitude",0).GetValue();
    string lat=root("Latitude",0).GetValue();
    d.first=strtod(lat.c_str(),NULL);
    d.second=strtod(lon.c_str(),NULL);
    return d;
}

double GeoIP::distance(unsigned long ip)
{
    if(!ip)
        return 0.0;
    std::pair<double,double> c=coord(ip);
    if(!m_bCoordSet)
    {
        m_Coord=coord(0);
        m_bCoordSet=true;
    }
    return distance(m_Coord.first,m_Coord.second,c.first,c.second);
}

double GeoIP::distance(unsigned long ip1,unsigned long ip2)
{
    std::pair<double,double> c1=coord(ip1);
    std::pair<double,double> c2=coord(ip2);
    return distance(c1.first,c1.second,c2.first,c2.second);
}

double GeoIP::distance(double lat1,double lon1, double lat2,double lon2)
{
    const double R=6371.0; // km
    double dLat=torad(lat2-lat1);
    double dLon=torad(lon2-lon1); 
    double a=sin(dLat/2)*sin(dLat/2)+cos(torad(lat1))*cos(torad(lat2))*sin(dLon/2)*sin(dLon/2); 
    double c=2*atan2(sqrt(a),sqrt(1-a)); 
    return R*c;
}


typedef std::vector<std::pair<std::string,std::string> > ENTRIES;

//read config file from /etc/geoip.conf
void load_hosts(ENTRIES& e)
{
    char str[4096];

    FILE *f=fopen("/etc/geoip.conf","rt");
    if(!f)
       return;
    while(fgets(str,4095,f))
    {
        str[strlen(str)-1]=0;
        char *p=str;
        while(*p && *p<=0x20)
            p++;
        if(*p=='#' || !*p)
           continue;
        char *pos=strchr(p,' ');
        if(!pos)
            pos=strchr(p,'\t');
        if(!pos)
            pos=strchr(p,'|');
        if(!pos)
           continue;
        *pos=0;
        pos++;
        while(*pos && *pos<=0x20)
            pos++;
        if(!*pos)
           continue;
        std::pair<std::string,std::string> pr;
        pr.first=p;
        pr.second=pos;
        e.push_back(pr);
    }    
    fclose(f);
}

#define DEFAULT_HOST "df.bestgrid.org"

int main(int argc,char *argv[])
{
    GeoIP gip;
    double mindist=10000000000.0;
    unsigned long ip=resolve(getenv("REMOTE_ADDR"));
    int mindistindex=-1;
    ENTRIES hosts;

    load_hosts(hosts);
    printf("Content-type: text/plain");
    if(!hosts.size())
    {
        printf("Status-Code: 301\n");
        printf("Location: %s\n",DEFAULT_HOST);
        printf("Location-Reason: no-configuration\n");
        printf("\n\n");
    }
    if(!hosts.size())
    {
        mindist=gip.distance(ip);
    }
    else
    {
        for(int i=0;i<hosts.size();i++)
        {
            double dist=gip.distance(resolve(hosts[i].first.c_str()),ip);
            if(dist<mindist)
            {
               mindist=dist;
               mindistindex=i;
            }
        }
    }
    if(mindistindex>=0)
    {
        printf("Status-Code: 301\n");
        printf("Location: %s\n",hosts[mindistindex].second.c_str());
        printf("GeoData: Dist=%lf, host=%s\n",mindist,hosts[mindistindex].first.c_str());
    }
    else
    {
        printf("Status-Code: 404\n");
	printf("\n\nDistance from the server: %.2lf\n",mindist);
    }
    printf("\n\n");

    return 0;
}

