//GeoIP-based Davis redirecting CGI
//G Soudlenkov, eResearch, Auckland, 2011
#ifndef GEOIP_H
#define GEOIP_H

#include <string>

//GeoIP handling service
class GeoIP
{
    public:
        GeoIP();
        ~GeoIP();
        std::pair<double,double> coord(unsigned long ip); //returns coordinates of the requested IP address
        double distance(unsigned long ip); //returns distance from the local host to the IP address
        double distance(unsigned long ip1,unsigned long ip2); //returns distance between two IP addresses

    protected:
        std::string request(unsigned long ip); //requests geolocation information about the IP. Returns XML stream or empty line if the request failed
        double distance(double lat1,double lon1,double lat2, double lon2); //calculate distance between two points
        std::pair<double,double> m_Coord; //local IP coordinates
	bool m_bCoordSet; //true if local IP coordinates have been set
};

#endif

