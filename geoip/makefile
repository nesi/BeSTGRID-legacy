CPP=g++
CFLAGS=-I ./xml -O
LFLAGS=-lm


all: geoip.cgi


geoip.cgi: geoip.cpp
	$(CPP) $(CFLAGS) $(LFLAGS) geoip.cpp xml/Elements.cpp xml/ErrorMessages.cpp xml/Utils.cpp xml/Writer.cpp xml/Reader.cpp -o geoip.cgi

clean:
	rm -f *.o geoip.cgi *~
