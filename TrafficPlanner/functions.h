#ifndef __functions__
#define __functions__

#include <cmath>
#include <vector>
#include <fstream>
#include <iostream>
#include <algorithm>
#include <functional>
#include <cassert>
#include <ctime> 

using namespace std;


//===========================================================================
#define MAX(a, b) (a < b ? b : a)
#define MIN(a, b) (a < b ? a : b)


//===========================================================================
//Common
const char c_CityFileName [] = "ceder_network.txt";
const char c_ODMatrixFileName [] = "ceder_demand.txt";

const double c_Epsilon = 0.0001;
const double c_Infinity = 1e10;
const double c_NoEdge = -1;

const double c_ShortestPathDeviationCar = 1.5;
const double c_DemandCoverageMinimum = 0.6;
const double c_BusCapacity = 80;
const double c_Desirable_Capacity_Portion = 1;
const double c_MinimumFrequency = 2;
const double c_TransferPenalty = 0;                  //In Minutes
const double c_Deviation_for_Elimination = 1.4;


//Symmetric Network
bool Symmetric_Network = true;

//Objective Function commons
const double C1 = 1;       //Waiting time value for an hour
const double C2 = 1;       //Empty seat hrs value for an hour
const double C3 = 1;       //Direcness value for an hour
const double C4 = 3;       //Fleet Size value
const double C5 = 2;       //Uncovered Demand value     

	//const double a1 = -400.0;
    //const double a2 = -90.0;
    //const double a3 = -300.0;
    //const double a4 = -2.5e4;
    //const double a5 = -60000;


//Genetic Algorithm
const double c_Crossover_Rate = 0.9;
const double c_Mutation_Rate = 0.008;
const unsigned c_Pop_Size = 300;
const unsigned c_MAX_Allowable_Generations = 150;
const double c_Best_Chroms_Survival_Rate = 2;
const unsigned Recent_Count = 30;		
const double RECENT_LIMIT = 500;
const double Select_Gene_Probability = 0.20;            //So sensitive
const unsigned c_Number_of_Cuts = 8;


//RGA s
const double Min_Route_Length = 10;               //In Minutes
const double Max_Route_Length = 50;               //In Minutes
const double Deviation_from_SP_RGA = 1.4;

//Print routes
const bool Print_routes = false;





//===========================================================================

struct CityMap
{
	CityMap (const char * filename)
	{
		ifstream fin (filename);
		
		fin >> V >> E;
		minutes.resize (V, vector<double>(V, c_NoEdge));
		meters.resize (V, vector<double>(V, c_NoEdge));
		
		for (unsigned i = 0; i < E; ++i)
		{
			unsigned from, to;
			fin >> from >> to;
			fin >> meters[from][to] >> minutes[from][to];
			meters[to][from] = meters[from][to];
			minutes[to][from] = minutes[from][to];
		}
	}
		
	unsigned V, E;
	vector<vector<double> > minutes;
	vector<vector<double> > meters;
};

//------------------------------------------------------------------------

struct DemandElement
{
	int from, to;
	double demand;

	bool operator < (const DemandElement & a) const
	{
		return demand < a.demand;
	}

};


struct Node_Route_Pair
{
	unsigned Node, Route;
};
struct Index_Route_Pair
{
	int Index, Route;
};

struct Assignment_Map_Element
{
	double Share;
	int St_number;
};
struct Ceder_Asgnmt_type
{
	int Start_idx;
	int End_idx;
	double share;
	bool AsgnmtCond;
	int length;
		bool operator < (const Ceder_Asgnmt_type & a) const
	{
		return length < a.length;
	}
};

//------------------------------------------------------------------------
//Type for paths

struct PathT
{
	vector<int> path;
	double len;
	double covereddemand;
	double frequency;
	double MLS_load;
	double Fleet_Size;

	PathT(int n = 0) : path(n), len(0), covereddemand(0) {};
	bool operator < (const PathT & a) const
	{
		return len < a.len;
	}
};
struct PathNo
{
	int path_number;
	vector<int> path;
};


struct Possible_Strategies
{
	vector<PathNo> paths;
	double In_Vehicle_Time;
	int Number_of_Transfers;
	bool IfShort;
	double StFrequency;

	bool operator<(const Possible_Strategies & a) const
	{
		return In_Vehicle_Time < a.In_Vehicle_Time;
	}
};

struct Chromosome_Type_001
{
	vector<bool> Chrom;
	double ff;

	double WT, EH, Dir, FS, UNCOVD;             //terms of fitness function
	bool ff_Condition;
	double maped_ff, prob_value;

	bool operator < (const Chromosome_Type_001 & a) const
	{
		return ff < a.ff;
	}
};




//========================================================================================================================
//======================End of Structs====================================================================================
//========================================================================================================================




void DFS (int s, int t, const CityMap & city, const double path_len_lim, PathT & cur_path, vector<PathT> & paths)
{
	//s: the starting node 
	//t: the end point
	//city: A Matrix from the type of CityMap contains network data
	//path_len_lim: is the limiting factor of path length. the value should be entered not percentage of shorthest path
	//cur_path: is a PathT type variable as a temp
	//paths: is a PathT type vector that gives the generated paths

	if (s == t)
	{
		paths.push_back(cur_path);
		return;
	}

	if (cur_path.path.size() == 0)
		cur_path.path.push_back(s);

	//int cur_node = cur_path[cur_path.size() - 1];
	for (unsigned i = 0; i < city.V; i++)
	{
		if (city.minutes[s][i] != c_NoEdge && find(cur_path.path.begin(), cur_path.path.end(), i) == cur_path.path.end() && cur_path.len + city.minutes[s][i] <= path_len_lim)
		{
			cur_path.path.push_back(i);
			cur_path.len += city.minutes[s][i];
			DFS(i, t, city, path_len_lim, cur_path, paths);
			cur_path.path.pop_back();
			cur_path.len -= city.minutes[s][i];
		}
	}
}

//------------------------------------------------------------------------

//city: A Matrix from the type of CityMap contains network data
//shortest_paths_len is a vecvec that gives the shorthest path lenghthes 

void Floyd (const CityMap & city, vector<vector<double> > & shortest_paths_len)
{
	shortest_paths_len = vector<vector<double> > (city.V, vector<double> (city.V));
	unsigned i, j, k;
	for (i = 0; i < city.V; i++)
		for (j = 0; j < city.V; j++)
			shortest_paths_len[i][j] = (city.minutes[i][j] == -1 ? (i == j ? 0 : c_Infinity) : city.minutes[i][j]);

	for (k = 0; k < city.V; k++)
		for (i = 0; i < city.V; i++)
			for (j = 0; j < city.V; j++)
				if (shortest_paths_len[i][k] + shortest_paths_len[k][j] < shortest_paths_len[i][j])
					shortest_paths_len[i][j]= shortest_paths_len[i][k] + shortest_paths_len[k][j];
}
//------------------------------------------------------------------------
void Calculate_Passing_Routes(unsigned Number_of_Nodes, vector<PathT> Chosen_Paths, 
							  vector<vector<Index_Route_Pair> > & Passing_Routes, vector<vector<int> > & Node_Route_Index)
{
	Passing_Routes.resize (Number_of_Nodes);
	Node_Route_Index.resize (Number_of_Nodes, vector<int> (Chosen_Paths.size(), -1));

	Index_Route_Pair tempo;
	tempo.Index = -1; 
	for (unsigned i = 0; i < Chosen_Paths.size(); i++)
		for (unsigned j = 0; j < Chosen_Paths[i].path.size(); j++)
		{
			tempo.Route = i;
			Passing_Routes[Chosen_Paths[i].path[j]].push_back(tempo);
		}
		for (unsigned i = 0; i < Passing_Routes.size(); i++)
			for (unsigned j = 0; j < Passing_Routes[i].size(); j++)   //Put this into the main loop----it is superstupid I know!
				for (unsigned k = 0; k < Chosen_Paths[Passing_Routes[i][j].Route].path.size(); k++)
					if ( Chosen_Paths[Passing_Routes[i][j].Route].path[k]== i)
					{
						Passing_Routes[i][j].Index = k;
						Node_Route_Index[i][Passing_Routes[i][j].Route] = k;
						break;
					}
}
//------------------------------------------------------------------------
void Calculate_Transfer_of_Route (vector<vector<Index_Route_Pair> > Passing_Routes, vector<PathT> Chosen_Paths, 
								  const unsigned Number_of_Routes, vector<vector<vector<int> > > & Transfer_Matrix, 
								  vector<vector<Node_Route_Pair> > & Transfer_Points)
{
//	if (Symmetric_Network == true)        // TODO: This is not working now fix it!
//	{
		
	Transfer_Points.resize(Number_of_Routes);
	Transfer_Matrix.resize(Number_of_Routes, vector<vector<int> > (Number_of_Routes, vector<int> (1, -1)));
	
	Node_Route_Pair temp;


		for (unsigned i = 0; i < Number_of_Routes; i++)
			for (unsigned j = 0; j < Chosen_Paths[i].path.size(); j++)
				for (unsigned k = 0; k < Passing_Routes[Chosen_Paths[i].path[j]].size(); k++)
					if (Passing_Routes[Chosen_Paths[i].path[j]][k].Route != i)
					{
						temp.Node = Chosen_Paths[i].path[j];
						temp.Route = Passing_Routes[Chosen_Paths[i].path[j]][k].Route;
						
						Transfer_Points[i].push_back(temp);
						if (!(i % 2 == 0 && temp.Route == i + 1))
							if (!(i % 2 == 1 && temp.Route == i - 1))
								if (Transfer_Matrix[i][temp.Route][0] != -1)
									Transfer_Matrix[i][temp.Route].push_back(temp.Node);
								else
									Transfer_Matrix[i][temp.Route][0] = temp.Node;
					}
//	}
}
//------------------------------------------------------------------------------------
void Calculate_Strategy_in_vehicle_time (const CityMap city, Possible_Strategies & tempstartegy)
{
	double temp = 0;
	for (unsigned i = 0; i < tempstartegy.paths.size(); i++)
		for (unsigned j = 0; j < tempstartegy.paths[i].path.size() - 1; j++)
			temp += city.minutes[tempstartegy.paths[i].path[j]][tempstartegy.paths[i].path[j + 1]];
	tempstartegy.In_Vehicle_Time = temp;

}
//------------------------------------------------------------------------------------
void Walk_on_Routes (const CityMap city, vector<PathT> Chosen_Paths, vector<vector<Index_Route_Pair> > Passing_Routes, 
							   vector<vector<Node_Route_Pair> > Transfer_Points, unsigned N,
							   vector<vector<vector<int> > > Transfer_Matrix, vector<vector<int> > Node_Route_Index, 
							   vector<vector<vector<Possible_Strategies> > > & Possible_Paths)
{
	Possible_Paths.resize (N, vector<vector<Possible_Strategies> > (N, vector<Possible_Strategies> (0)));
	PathNo tempath;
	PathNo tempath2;
	Possible_Strategies tempstartegy;
	for (unsigned m = 0; m < Chosen_Paths.size(); m++)
	{
		tempath.path_number = m; 
		tempath.path.clear();
		for (unsigned i = 0; i < Chosen_Paths[m].path.size() - 1; i++)
		{
			tempath.path.clear();
			tempath.path.push_back(Chosen_Paths[m].path[i]);
			double temp_in_vehicle_time = 0;

			for (unsigned j = i + 1; j < Chosen_Paths[m].path.size(); j++)
			{
				tempath.path.push_back(Chosen_Paths[m].path[j]);
				temp_in_vehicle_time += city.minutes[Chosen_Paths[m].path[j - 1]][Chosen_Paths[m].path[j]];
				tempstartegy.paths.clear();
				tempstartegy.paths.push_back(tempath);
				tempstartegy.In_Vehicle_Time = temp_in_vehicle_time;
				tempstartegy.Number_of_Transfers = 0;

				Possible_Paths[Chosen_Paths[m].path[i]][Chosen_Paths[m].path[j]].push_back(tempstartegy);
			}
		}
	}
	
//	PathNo tempath;
//	PathNo tempath2;
//	Possible_Strategies tempstartegy;
	tempstartegy.paths.clear();
	tempstartegy.Number_of_Transfers = 1;
	tempstartegy.In_Vehicle_Time = 0;
	for (unsigned i = 0; i < Chosen_Paths.size(); i++)
		for (unsigned j = 0; j < Chosen_Paths.size(); j++)
			for (unsigned k = 0; k < Transfer_Matrix[i][j].size(); k++)
				if (Transfer_Matrix[i][j][k] != -1)
				{
					tempath.path.clear();
					tempath2.path.clear();
					tempath.path_number = i;
					tempath2.path_number = j;

					if (Node_Route_Index[Transfer_Matrix[i][j][k]][j] < Chosen_Paths[j].path.size() - 1)
						for (int p = 0; p < Node_Route_Index[Transfer_Matrix[i][j][k]][i]; p++)    // check for symmetric & the "-"
						{
							tempath.path.clear();
							for (int kk = p; kk <= Node_Route_Index[Transfer_Matrix[i][j][k]][i]; kk++)                 //Check for <=
								tempath.path.push_back(Chosen_Paths[i].path[kk]);
							
							tempath2.path.clear();
							tempath2.path.push_back(Transfer_Matrix[i][j][k]);
							for (unsigned q = Node_Route_Index[Transfer_Matrix[i][j][k]][j] + 1; q < Chosen_Paths[j].path.size(); q++)
							{
								tempstartegy.paths.clear();
								tempstartegy.paths.push_back(tempath);
								tempath2.path.push_back(Chosen_Paths[j].path[q]);
								tempstartegy.paths.push_back(tempath2);
								tempstartegy.In_Vehicle_Time = 0;

							
								//TODO: you should check for avoiding any loops!
								if (Chosen_Paths[i].path[p] != Chosen_Paths[j].path[q])     //Go around and come back to itself???!
								{
									Calculate_Strategy_in_vehicle_time (city, tempstartegy);
									Possible_Paths[Chosen_Paths[i].path[p]][Chosen_Paths[j].path[q]].push_back(tempstartegy);
								}
							}
						}
			}
}//------------------------------------------------------------------------------------

void Assign_to_Strategy (double Demand, Possible_Strategies Strategy, vector<vector<vector<double> > > & Network_Flow)
{
	for (unsigned i = 0; i < Strategy.paths.size(); i++)
		for (unsigned j = 0; j < Strategy.paths[i].path.size() - 1; j++)
			Network_Flow[Strategy.paths[i].path[j]][Strategy.paths[i].path[j+1]][Strategy.paths[i].path_number] += Demand;
}
//------------------------------------------------------------------------------------
void All_or_Nothing_Assignment (vector<vector<double> > ODmatrix, vector<vector<vector<Possible_Strategies> > > & Possible_Paths,
								vector<PathT> Chosen_Paths, vector<vector<vector<double> > > & Network_Flow, double Total_Demand, 
								double & Covered_Demand_0_transfer, double & Covered_Demand_1_transfer, double & Un_Covered_Demand, 
								vector<vector<double> > & Bus_Shortest_Path, vector<vector<vector<Assignment_Map_Element> > > & Assignment_Map)
{
	unsigned N = ODmatrix.size();
	Network_Flow.resize(N, vector<vector<double> > (N, vector<double> (Chosen_Paths.size(), 0)));
	Covered_Demand_0_transfer = 0;
	Covered_Demand_1_transfer = 0;
	Bus_Shortest_Path.resize(N, vector<double> (N, -1));
	Assignment_Map.resize(N, vector<vector<Assignment_Map_Element> > (N, vector<Assignment_Map_Element> (0)));

	for (unsigned i = 0; i < ODmatrix.size(); i++)
		for (unsigned j = 0; j < ODmatrix.size(); j++)
		{
			if (Possible_Paths[i][j].size() != 0)
			{
				sort(Possible_Paths[i][j].begin(), Possible_Paths[i][j].end());

				Assign_to_Strategy (ODmatrix[i][j],Possible_Paths[i][j][0], Network_Flow);   // It is sorted so the shorthest is indexed ZERO
				
				// Making the assignment map
				Assignment_Map_Element tempassign;
				tempassign.Share = 1;
				tempassign.St_number = 0;
				Assignment_Map[i][j].push_back(tempassign);

				if (Possible_Paths[i][j][0].Number_of_Transfers == 0)
					Covered_Demand_0_transfer += ODmatrix[i][j];
				else
					Covered_Demand_1_transfer += ODmatrix[i][j];
				Bus_Shortest_Path[i][j] = Possible_Paths[i][j][0].In_Vehicle_Time;             //No Transfer impact, if needed add it in the if loop above!!
			}
		}
		Un_Covered_Demand = Total_Demand - (Covered_Demand_0_transfer + Covered_Demand_1_transfer);

}

//------------------------------------------------------------------------------------
void Ceder_Assignment (vector<vector<double> > ODmatrix, vector<vector<vector<Possible_Strategies> > > & Possible_Paths,
					   vector<PathT> Chosen_Paths, vector<vector<vector<double> > > & Network_Flow,  double Total_Demand, 
					   double & Covered_Demand_0_transfer, double & Covered_Demand_1_transfer, double & Un_Covered_Demand,
					   vector<vector<vector<Assignment_Map_Element> >  > & Assignment_Map)
{
	//eliminate long routes "this function would require an eliminated Possible_Paths and sorted"
	
//==================================
	vector<Ceder_Asgnmt_type> Shares;
	Un_Covered_Demand = 0;
	Assignment_Map_Element tempassignmap;
	Network_Flow.clear();
	Network_Flow.resize(ODmatrix.size(), vector<vector<double> > (ODmatrix.size(), vector<double> (Chosen_Paths.size(), 0)));
	Assignment_Map.clear();   //TODO: check for the size of assignment map
	Assignment_Map.resize(ODmatrix.size(), vector<vector<Assignment_Map_Element> > (ODmatrix.size(), vector<Assignment_Map_Element> (0)));

	for (unsigned i = 0; i < ODmatrix.size(); i++)
		for (unsigned j = 0; j < ODmatrix.size(); j++)
		{
			if (Possible_Paths[i][j].size() == 0)
				Un_Covered_Demand += ODmatrix[i][j];

			if (i != j && Possible_Paths[i][j].size() != 0)
			{
				//Define and calculate Strategy frequency:   //here the same for one route paths and minimum for two route paths (for changing it to the first part route omit the else and use zero indexed path  frequency!!!
				for (unsigned k = 0; k < Possible_Paths[i][j].size(); k++)
					if (Possible_Paths[i][j][k].Number_of_Transfers == 0)
						Possible_Paths[i][j][k].StFrequency = Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency;
					else
						Possible_Paths[i][j][k].StFrequency = MIN(Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency, Chosen_Paths[Possible_Paths[i][j][k].paths[1].path_number].frequency);


				Shares.clear();
				Ceder_Asgnmt_type tempShares;
				tempShares.Start_idx = 0;
				tempShares.End_idx = Possible_Paths[i][j].size() - 1;   //TODO: -1 ?
				tempShares.share = 1;
				tempShares.AsgnmtCond = false;
				tempShares.length = tempShares.End_idx - tempShares.End_idx;
				
				Shares.push_back(tempShares);

				bool end_condition = false;

				while (end_condition == false)
				{
					if (Possible_Paths[i][j][Shares[0].Start_idx].In_Vehicle_Time == 
						Possible_Paths[i][j][Shares[0].End_idx].In_Vehicle_Time)
					{
						double freqSum = 0;
						for (unsigned q = Shares[0].Start_idx; q < Shares[0].End_idx + 1; q++)
							freqSum += Possible_Paths[i][j][q].StFrequency;
						for (unsigned m = Shares[0].Start_idx; m < Shares[0].End_idx + 1; m++) /// TODO: chek for the +1
						{
							tempShares.Start_idx = m;
							tempShares.End_idx = m;
							tempShares.length = 0; ///??????????????????
							tempShares.share = (Possible_Paths[i][j][m].StFrequency / freqSum) * Shares[0].share;
							tempShares.AsgnmtCond = true;

							Shares.push_back(tempShares);
						}
						Shares.erase(Shares.begin());       // TODO: Check it 

					}
					else
					{
						//Calculate the sum of travel time for finding the average
						double temptraveltime = 0;
						double average_travel_time  = 0;
						
						for (unsigned q = Shares[0].Start_idx; q < Shares[0].End_idx + 1; q++)   //+1 ????? seems ok
							temptraveltime += Possible_Paths[i][j][q].In_Vehicle_Time;
						
						average_travel_time = temptraveltime / (Shares[0].End_idx - Shares[0].Start_idx + 1);   ///+1 ??? seems ok

						double temptt1 = 0;
						double temptt2 = 0;
						double avgtt1 = 0;
						double avgtt2 = 0;
						double tempfreq1 = 0;
						double tempfreq2 = 0;
						double avgfreq1 = 0;
						double avgfreq2 = 0;
						
						int counter = 0;
						for (unsigned m = Shares[0].Start_idx; m < Shares[0].End_idx + 1; m++)    //It would never reach to the end but +1 is needed 
							if (Possible_Paths[i][j][m].In_Vehicle_Time < average_travel_time)
							{
								temptt1 += Possible_Paths[i][j][m].In_Vehicle_Time;
								tempfreq1 += Possible_Paths[i][j][m].StFrequency;
								counter++;
							}
							else
							{
								tempShares.Start_idx = m;
								tempShares.End_idx = Shares[0].End_idx;
								Shares[0].End_idx = m - 1;  // or m ? That's right!
								break;
							}

							assert(Shares[0].Start_idx - Shares[0].Start_idx + 1 == counter);
							avgfreq1 = tempfreq1 / counter;
							avgtt1 = temptt1 / counter;

							counter = 0;
							for (unsigned q = tempShares.Start_idx; q < tempShares.End_idx + 1; q++)  //TODO: or +1? needed added
							{
								temptt2 += Possible_Paths[i][j][q].In_Vehicle_Time;
								tempfreq2 += Possible_Paths[i][j][q].StFrequency;
								counter++;
							}
							assert (tempShares.End_idx - tempShares.Start_idx + 1 == counter);
							avgtt2 = temptt2 / counter;
							avgfreq2 = tempfreq2 / counter;

							Shares[0].length = Shares[0].End_idx - Shares[0].Start_idx;
							tempShares.length = tempShares.End_idx - tempShares.Start_idx;

							if (avgtt2 - avgtt1 <= (60 / avgfreq1))
							{
								tempShares.share = ((avgfreq2 * ( 1 - avgfreq1 * ((avgtt2 - avgtt1)) / 60) / (avgfreq1 + avgfreq2)) * Shares[0].share);
								Shares[0].share  = ((avgfreq1 * ( 1 + avgfreq1 * ((avgtt2 - avgtt1)) / 60) / (avgfreq1 + avgfreq2)) * Shares[0].share);
							}
							else
								tempShares.share = 0;

							Shares.push_back(tempShares);

					}
					//Checking for the end
					end_condition = true;
					for (unsigned p = 0; p < Shares.size(); p++)
						if (Shares[p].End_idx != Shares[p].Start_idx)
						{
							end_condition = false;
							break;
						}

						sort(Shares.begin(), Shares.end());
						reverse(Shares.begin(), Shares.end());
				}
							// You have shares!!!!! haha
					for (unsigned r = 0; r < Shares.size(); r++)
					{
						assert (Shares[r].Start_idx == Shares[r].End_idx);
						if (Shares[r].share != 0)
						{
							Assign_to_Strategy(Shares[r].share * ODmatrix[i][j], Possible_Paths[i][j][Shares[r].Start_idx], Network_Flow);
							// Making the assignment map
							tempassignmap.Share = Shares[r].share;
							tempassignmap.St_number = Shares[r].Start_idx;
							Assignment_Map[i][j].push_back(tempassignmap);

							if (Possible_Paths[i][j][Shares[r].Start_idx].Number_of_Transfers == 0)
								Covered_Demand_0_transfer += Shares[r].share  * ODmatrix[i][j];
							else
								Covered_Demand_1_transfer += Shares[r].share  * ODmatrix[i][j];
						}
					}
				}
			}
//func
}
//------------------------------------------------------------------------------------

//TODO: add fleet to this
void Frequecy_Setting_M2(vector<PathT> & Chosen_Paths, vector<vector<vector<double> > > Network_Flow, double & Total_Fleet_Size)   //This Network flow matrix is HUGE!!!!!!! I know
{
	Total_Fleet_Size = 0;
	for (unsigned i = 0; i < Chosen_Paths.size(); i+= 2) // For "aller" and "retour" the same frequency is determined.
	{
		double temp = 0;
		//Aller
		for (unsigned j = 0; j < Chosen_Paths[i].path.size() - 1; j++)
			if (Network_Flow[Chosen_Paths[i].path[j]][Chosen_Paths[i].path[j+1]][i] > temp)
				temp = Network_Flow[Chosen_Paths[i].path[j]][Chosen_Paths[i].path[j+1]][i];
		//Retour
		for (unsigned j = 0; j < Chosen_Paths[i+1].path.size() - 1; j++)
			if (Network_Flow[Chosen_Paths[i+1].path[j]][Chosen_Paths[i+1].path[j+1]][i+1] > temp)
				temp = Network_Flow[Chosen_Paths[i+1].path[j]][Chosen_Paths[i+1].path[j+1]][i+1];   ///MLS is calculated in both directions of the route

		Chosen_Paths[i].MLS_load = temp;
		Chosen_Paths[i + 1].MLS_load = temp;
		Chosen_Paths[i].frequency = Chosen_Paths[i].MLS_load / (c_Desirable_Capacity_Portion * c_BusCapacity);
		if (Chosen_Paths[i].frequency < c_MinimumFrequency)
			Chosen_Paths[i].frequency = c_MinimumFrequency;
		Chosen_Paths[i + 1].frequency = Chosen_Paths[i].frequency;
		
		//------Fleet Size-----------------------
		Chosen_Paths[i].Fleet_Size = Chosen_Paths[i].len / (60 / Chosen_Paths[i].frequency);
		Total_Fleet_Size += Chosen_Paths[i].Fleet_Size;
		Chosen_Paths[i+1].Fleet_Size = Chosen_Paths[i+1].len / (60 / Chosen_Paths[i+1].frequency);
		Total_Fleet_Size += Chosen_Paths[i+1].Fleet_Size;
	}
}

//----------------------------------------------------------------------------------------

void TT_based_Assignment (vector<vector<double> > ODmatrix, vector<vector<vector<Possible_Strategies> > > & Possible_Paths,
						  vector<PathT> Chosen_Paths, vector<vector<vector<double> > > & Network_Flow,  double Total_Demand, 
						  double & Covered_Demand_0_transfer, double & Covered_Demand_1_transfer, double & Un_Covered_Demand,
						  vector<vector<vector<Assignment_Map_Element> > > & Assignment_Map, double Convegence_factor,
						  vector<vector<double> > & frequency_results_TT_based, double & total_fleet_size)
{
	Un_Covered_Demand = 0;
	Covered_Demand_0_transfer = 0;
	Covered_Demand_1_transfer = 0;
	Assignment_Map_Element tempassignmap;
	Network_Flow.clear();
	Network_Flow.resize(ODmatrix.size(), vector<vector<double> > (ODmatrix.size(), vector<double> (Chosen_Paths.size(), 0)));
	Assignment_Map.clear();   //TODO: check for the size of assignment map
	Assignment_Map.resize(ODmatrix.size(), vector<vector<Assignment_Map_Element> > (ODmatrix.size(), vector<Assignment_Map_Element> (0)));


	//main loop - first step Assignment procedure without the headway
	for (unsigned i = 0; i < ODmatrix.size(); i++)
		for (unsigned j = 0; j < ODmatrix.size(); j++)
		{
			if (Possible_Paths[i][j].size() == 0)
				Un_Covered_Demand += ODmatrix[i][j];

			if (i != j && Possible_Paths[i][j].size() != 0)
			{ 
				//Define and calculate Strategy frequency: and 1/T summation   //here the same for one route paths and minimum for two route paths (for changing it to the first part route omit the else and use zero indexed path  frequency!!!
	
				double tempttsum = 0;
				for (unsigned k = 0; k < Possible_Paths[i][j].size(); k++)
				{
					if (Possible_Paths[i][j][k].Number_of_Transfers == 0)
						Possible_Paths[i][j][k].StFrequency = Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency;
					else
						Possible_Paths[i][j][k].StFrequency = MIN(Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency, Chosen_Paths[Possible_Paths[i][j][k].paths[1].path_number].frequency);

					tempttsum += 1 / Possible_Paths[i][j][k].In_Vehicle_Time;
				}

				for (unsigned m = 0; m < Possible_Paths[i][j].size(); m++)
				{
					tempassignmap.St_number = m;
					tempassignmap.Share = ((1 / Possible_Paths[i][j][m].In_Vehicle_Time) / tempttsum);
					Assignment_Map[i][j].push_back(tempassignmap);
				}

				// You have shares!!!!! haha
				for (unsigned r = 0; r < Assignment_Map[i][j].size(); r++)
				{
					if (Assignment_Map[i][j][r].Share != 0)
					{
						Assign_to_Strategy(Assignment_Map[i][j][r].Share * ODmatrix[i][j], Possible_Paths[i][j][Assignment_Map[i][j][r].St_number], Network_Flow);
						if (Possible_Paths[i][j][Assignment_Map[i][j][r].St_number].Number_of_Transfers == 0)
							Covered_Demand_0_transfer += Assignment_Map[i][j][r].Share * ODmatrix[i][j];
						else
							Covered_Demand_1_transfer += Assignment_Map[i][j][r].Share * ODmatrix[i][j];
					}
					else
						cout << "Bug 1";
				}
			}
		}

		Frequecy_Setting_M2(Chosen_Paths, Network_Flow, total_fleet_size);

		vector<double> tempfreqvec;
		tempfreqvec.clear();
		for (unsigned k = 0; k < Chosen_Paths.size(); k++)
		{
			tempfreqvec.push_back(Chosen_Paths[k].frequency);
			assert(Chosen_Paths[k].frequency != 0);
		}
		frequency_results_TT_based.push_back(tempfreqvec);

		//If you need Network flow here keep it some place after this vanished !!!!


//============End of first step assignment==================

	bool Stop_Condition = false;
	int counter = 0;

	while (Stop_Condition == false)
	{
		Covered_Demand_0_transfer = 0;
		Covered_Demand_1_transfer = 0;
		Network_Flow.clear();
		Network_Flow.resize(ODmatrix.size(), vector<vector<double> > (ODmatrix.size(), vector<double> (Chosen_Paths.size(), 0)));
		Assignment_Map.clear();   //TODO: check for the size of assignment map
		Assignment_Map.resize(ODmatrix.size(), vector<vector<Assignment_Map_Element> > (ODmatrix.size(), vector<Assignment_Map_Element> (0)));

		for (unsigned i = 0; i < ODmatrix.size(); i++)
			for (unsigned j = 0; j < ODmatrix.size(); j++)
			{
				if (i != j && Possible_Paths[i][j].size() != 0)
				{ 
					//Define and calculate Strategy frequency: and 1/T summation   //here the same for one route paths and minimum for two route paths (for changing it to the first part route omit the else and use zero indexed path  frequency!!!
		
					double tempttsum = 0;
					for (unsigned k = 0; k < Possible_Paths[i][j].size(); k++)
					{
						if (Possible_Paths[i][j][k].Number_of_Transfers == 0)
							Possible_Paths[i][j][k].StFrequency = Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency;
						else
							Possible_Paths[i][j][k].StFrequency = MIN(Chosen_Paths[Possible_Paths[i][j][k].paths[0].path_number].frequency, Chosen_Paths[Possible_Paths[i][j][k].paths[1].path_number].frequency);

						tempttsum += 1 / (Possible_Paths[i][j][k].In_Vehicle_Time + (30 / Possible_Paths[i][j][k].StFrequency));   //30/f = H/2
					}

					for (unsigned m = 0; m < Possible_Paths[i][j].size(); m++)
					{
						tempassignmap.St_number = m;
						tempassignmap.Share = (1 / (Possible_Paths[i][j][m].In_Vehicle_Time + (30 / Possible_Paths[i][j][m].StFrequency)) / tempttsum);
						Assignment_Map[i][j].push_back(tempassignmap);
					}

					// You have shares!!!!! haha
					for (unsigned r = 0; r < Assignment_Map[i][j].size(); r++)
					{
						if (Assignment_Map[i][j][r].Share != 0)
						{
							Assign_to_Strategy(Assignment_Map[i][j][r].Share * ODmatrix[i][j], Possible_Paths[i][j][Assignment_Map[i][j][r].St_number], Network_Flow);
							if (Possible_Paths[i][j][Assignment_Map[i][j][r].St_number].Number_of_Transfers == 0)
								Covered_Demand_0_transfer += Assignment_Map[i][j][r].Share * ODmatrix[i][j];
							else
								Covered_Demand_1_transfer += Assignment_Map[i][j][r].Share * ODmatrix[i][j];
						}
						else
							cout << "Bug 1";
					}
				}
			}
		//---------------------------------------------
		Frequecy_Setting_M2(Chosen_Paths, Network_Flow, total_fleet_size);

		tempfreqvec.clear();
		for (unsigned k = 0; k < Chosen_Paths.size(); k++)
			tempfreqvec.push_back(Chosen_Paths[k].frequency);
		frequency_results_TT_based.push_back(tempfreqvec);
		
		counter++;
		if (counter == 20)
			Stop_Condition = true;
	}
	}

//------------------------------------------------------------------------------------


//------------------------------------------------------------------------------------
void Eliminate_long_Routes (double Deviation, vector<vector<double> > Bus_Shortest_Path, 
							vector<vector<vector<Possible_Strategies> > > & Possible_Paths)   //it requires the sorted Possible Paths
{
	for (unsigned i = 0; i < Possible_Paths.size(); i++)
		for (unsigned j = 0; j < Possible_Paths.size(); j++)
			if (Possible_Paths[i][j].size() > 1)
				for (unsigned k = 1; k < Possible_Paths[i][j].size(); k++)    
					if (Possible_Paths[i][j][k].In_Vehicle_Time > (Deviation * Bus_Shortest_Path[i][j]))
					{
						Possible_Paths[i][j].erase(Possible_Paths[i][j].begin() + k);
						k--;                     //Ask Ehsan about that k--     seems stupid
					}
}
void WT_EH_Dir_OF001_Calculation (vector<vector<double> > ODmatrix, 
								  vector<vector<vector<Possible_Strategies> > > & Possible_Paths,
								  vector<PathT> Chosen_Paths, 
								  vector<vector<vector<double> > > & Network_Flow, 
								  CityMap city, 
								  vector<vector<vector<Assignment_Map_Element> > > & Assignment_Map, 
								  double & Total_Waiting_Time,
								  vector<vector<double> > & Car_Shortest_Path, 
								  double & Total_Empty_Seat_Minutes, 
								  double & Total_Directness_from_Shorthest_Path,
								  vector<vector<Index_Route_Pair> > & Passing_Routes,
								  vector<vector<int> > & Node_Route_Index,
								  vector<vector<Node_Route_Pair> > & Transfer_Points,
								  vector<vector<vector<int> > > & Transfer_Matrix,
								  double & Covered_Demand_0_transfer,
								  double & Covered_Demand_1_transfer,
								  double & Un_Covered_Demand,
								  vector<vector<double> > & Bus_Shortest_Path,
								  double Total_Demand,
								  double & Total_Fleet_Size)
{
//------Initiation-------------------------------------------------------------------------------------------
	Passing_Routes.clear();
	Node_Route_Index.clear();
	Transfer_Points.clear();
	Transfer_Matrix.clear();
	Possible_Paths.clear();
	Network_Flow.clear();
	Bus_Shortest_Path.clear();
	Assignment_Map.clear();
	//Car_Shortest_Path.clear();   //As it is coming from outside!

//------Run Required Functions----------------------------------------------------------------------

	Calculate_Passing_Routes (city.V, Chosen_Paths, Passing_Routes, Node_Route_Index);
	Calculate_Transfer_of_Route (Passing_Routes, Chosen_Paths, Chosen_Paths.size(), Transfer_Matrix, Transfer_Points);
	Walk_on_Routes (city, Chosen_Paths, Passing_Routes, Transfer_Points, city.V, Transfer_Matrix, Node_Route_Index, Possible_Paths);
	
	All_or_Nothing_Assignment(ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow,Total_Demand, Covered_Demand_0_transfer, Covered_Demand_1_transfer,Un_Covered_Demand, Bus_Shortest_Path, Assignment_Map);
	Frequecy_Setting_M2 (Chosen_Paths, Network_Flow, Total_Fleet_Size);
	//Floyd (city, Car_Shortest_Path);   Moved to RGA 
	Eliminate_long_Routes (c_Deviation_for_Elimination, Bus_Shortest_Path, Possible_Paths);




	Total_Waiting_Time = 0;
	Total_Empty_Seat_Minutes = 0; 
	Total_Directness_from_Shorthest_Path = 0;



//-------Total Waiting Time----(in minutes)-------------------------------------------------------------------
//-------Directness from Shortest Path------------------------------------------------------------------------

	for (unsigned i = 0; i < ODmatrix.size(); i++)
		for (unsigned j = 0; j < ODmatrix.size(); j++)
			if (Assignment_Map[i][j].size() != 0)
				for (unsigned k = 0; k < Assignment_Map[i][j].size(); k++)
				{
					Total_Directness_from_Shorthest_Path += ((ODmatrix[i][j] * Assignment_Map[i][j][k].Share) * (Possible_Paths[i][j][k].In_Vehicle_Time - Car_Shortest_Path[i][j]));   //Transfer effect is not considered in here (in minutes)
					for (unsigned m = 0; m < Possible_Paths[i][j][k].paths.size(); m++)
						Total_Waiting_Time += ((ODmatrix[i][j] * Assignment_Map[i][j][k].Share) * (60 / Chosen_Paths[Possible_Paths[i][j][k].paths[m].path_number].frequency) / 2); // in Minutes
				}	
	//Convert to hours
	Total_Directness_from_Shorthest_Path /= 60;
	Total_Waiting_Time /= 60;


//-------Total Empty Seat Minutes----------------------------------------------------------------------------
	for (unsigned i = 0; i < Chosen_Paths.size(); i++)	
		for (unsigned k = 0; k < Chosen_Paths[i].path.size() - 1; k++)
			Total_Empty_Seat_Minutes += ((Chosen_Paths[i].MLS_load - Network_Flow[Chosen_Paths[i].path[k]][Chosen_Paths[i].path[k+1]][i]) * city.minutes[Chosen_Paths[i].path[k]][Chosen_Paths[i].path[k+1]]);
	//Convert to hours
	Total_Empty_Seat_Minutes /= 60;


}
//------------------------------------------------------------------------------------
void Covered_Demand(const vector<vector<double> > & ODmatrix, PathT & route)
{
	unsigned i, j;
	for (i = 0; i < route.path.size(); i++)
		for (j = i; j < route.path.size(); j++)
			route.covereddemand += (ODmatrix[route.path[i]][route.path[j]] + ODmatrix[route.path[j]][route.path[i]]);
}

void RGA001_Route_Generation_Algorithm (CityMap city,vector<vector<double> > & Car_Shortest_Path, 
										vector<PathT> & All_Feasible_Paths)
{
	Floyd (city, Car_Shortest_Path);	
	
	PathT cur_path;
	vector<PathT> Paths;
//	double temptime;
	for (int i = 0; i < city.V; i++)    // V  or E??????
		for (int j = i + 1; j < city.V; j++)
		{

			cur_path.path.clear();
			Paths.clear();

			DFS (i, j, city, Deviation_from_SP_RGA * Car_Shortest_Path[i][j], cur_path, Paths);

			for (int k = 0; k < Paths.size(); k++)
				if (Paths[k].len > Min_Route_Length && Paths[k].len < Max_Route_Length)
					All_Feasible_Paths.push_back(Paths[k]);
		}
}
//------------------------------------------------------------------------


//NOT CHECKED  NOT CHECKED   NOT CHECKED  NOT CHECKED  NOT CHECKED  NOT CHECKED  NOT CHECKED  NOT CHECKED
void Cross_Over (Chromosome_Type_001 Parent_One, Chromosome_Type_001 Parent_Two, int Number_of_Cuts, 
				 Chromosome_Type_001 & Child_One, Chromosome_Type_001 & Child_Two)
{
	Child_One.Chrom.clear();
	Child_Two.Chrom.clear();
	
	bool index = 0;
	unsigned counter = 0;
	unsigned Chrome_length = Parent_One.Chrom.size();
	unsigned cut_length = int(Chrome_length / Number_of_Cuts);
	Child_One.Chrom.resize (Chrome_length, 0);
	Child_Two.Chrom.resize (Chrome_length, 0);

	while (counter < Chrome_length)
	{
		for (unsigned i = 0; i < cut_length; i++)
		{
			if (index)
			{
				Child_One.Chrom[counter] = Parent_One.Chrom[counter];
				Child_Two.Chrom[counter] = Parent_Two.Chrom[counter];
				counter++;
			}
			else
			{
				Child_One.Chrom[counter] = Parent_Two.Chrom[counter];
				Child_Two.Chrom[counter] = Parent_One.Chrom[counter];
				counter++;
			}
			if (counter == Chrome_length)
				break;
		}
			if (index)
				index = false;
			else 
				index = true;
	}

	Child_One.ff_Condition = false;
	Child_Two.ff_Condition = false;
}
//------------------------------------------------------------------------
void Chrom_to_Chosen_Paths (Chromosome_Type_001 Chromosome, vector<PathT> & Chosen_Paths, vector<PathT> All_Feasible_Paths)
{
	//needing a blank Chosen_Path Bro!!!!!            This would add the retour as well!
	Chosen_Paths.clear();
	PathT tempath;

	for (unsigned i = 0; i < Chromosome.Chrom.size(); i++)
		if (Chromosome.Chrom[i])
		{
			tempath.path.clear();
			tempath.len = 0;
			tempath = All_Feasible_Paths[i];
			Chosen_Paths.push_back(tempath);
			reverse (tempath.path.begin(), tempath.path.end());
			Chosen_Paths.push_back(tempath);
		}
}

//------------------------------------------------------------------------
void GA001 (vector<vector<double> > ODmatrix,
			vector<vector<vector<Possible_Strategies> > > & Possible_Paths,
			vector<PathT> Chosen_Paths, 
			vector<vector<vector<double> > > & Network_Flow, 
			CityMap city, 
			vector<vector<vector<Assignment_Map_Element> > > & Assignment_Map, 
			double & Total_Waiting_Time,
			vector<vector<double> > & Car_Shortest_Path, 
			double & Total_Empty_Seat_Minutes, 
			double & Total_Directness_from_Shorthest_Path,
			vector<vector<Index_Route_Pair> > & Passing_Routes,
			vector<vector<int> > & Node_Route_Index,
			vector<vector<Node_Route_Pair> > & Transfer_Points,
			vector<vector<vector<int> > > & Transfer_Matrix,
			double & Covered_Demand_0_transfer,
			double & Covered_Demand_1_transfer,
			double & Un_Covered_Demand,
			vector<vector<double> > & Bus_Shortest_Path,
			double Total_Demand,
			vector<PathT> & All_Feasible_Paths,
			double Total_Fleet_Size,
			vector<Chromosome_Type_001> & GA001_Results)
{
	//srand(time(0));
    srand(251969);

	
	unsigned Chromosome_Length = All_Feasible_Paths.size();
	vector<Chromosome_Type_001> cur_population (c_Pop_Size);
	vector<Chromosome_Type_001> temp_Population (c_Pop_Size);

//Built the initial Population
	for (unsigned i = 0; i < c_Pop_Size; i++)
	{
		for (unsigned j = 0; j < Chromosome_Length; j++)
		{
			double random_number = rand();
			if ((random_number / RAND_MAX) < Select_Gene_Probability)
				cur_population[i].Chrom.push_back(1);
			else
				cur_population[i].Chrom.push_back(0);
		}
		cur_population[i].ff_Condition = false;
	}
//Calculate fitness for Initial Population
	for (unsigned i = 0; i < c_Pop_Size; i++)
		{
			if (cur_population[i].ff_Condition == false)
			{
				Chrom_to_Chosen_Paths (cur_population[i], Chosen_Paths, All_Feasible_Paths);
				
				WT_EH_Dir_OF001_Calculation (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map,
					Total_Waiting_Time, Car_Shortest_Path, Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path,
					Passing_Routes, Node_Route_Index, Transfer_Points, Transfer_Matrix, Covered_Demand_0_transfer, 
					Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, Total_Demand, Total_Fleet_Size);

				cur_population[i].WT = Total_Waiting_Time;
				cur_population[i].EH = Total_Empty_Seat_Minutes;
				cur_population[i].Dir = Total_Directness_from_Shorthest_Path;
				cur_population[i].FS = Total_Fleet_Size;
				cur_population[i].UNCOVD = Un_Covered_Demand;
				
				cur_population[i].ff = C1 * cur_population[i].WT + C2 * cur_population[i].EH + C3 * cur_population[i].Dir +
					C4 * cur_population[i].FS + C5 * cur_population[i].UNCOVD;

				cur_population[i].ff_Condition = true;
			}
		}

//-----Main Loop-----------------------------------------------------------
	unsigned Gen_counter = 0;
	Chromosome_Type_001 Child_One;
	Chromosome_Type_001 Child_Two;

	vector<Chromosome_Type_001> Parents;
	

	while (Gen_counter < c_MAX_Allowable_Generations)
	{
	
		//Calculate fitness for new Population and the ff summation
		double maped_ff_Summation = 0;

		sort (cur_population.begin(), cur_population.end());

		double x1 = 0;
		double x2 = -100;
		
		for (unsigned i = 0; i < cur_population.size(); i++)
		{
			assert(cur_population[i].ff_Condition == true);        // Just a check-
			//assert(cur_population[0].ff - cur_population[c_Pop_Size -1].ff != 0);
			cur_population[i].maped_ff = double(x1 + ((cur_population[i].ff - cur_population[c_Pop_Size -1].ff) * ((x2 - x1) / 
				(cur_population[0].ff - cur_population[c_Pop_Size -1].ff))));
			maped_ff_Summation += cur_population[i].maped_ff;
		}
		
		GA001_Results.push_back(cur_population[0]);            //Writing the results

		temp_Population.clear();

		//Elicitism-----Putting best two, intacxt--------------
		temp_Population.push_back(cur_population[0]);
		temp_Population.push_back(cur_population[1]);

		while (temp_Population.size() < c_Pop_Size)
		{
			//Finding Mates
			Parents.clear();
			unsigned ch_counter = 0;
			while (Parents.size() < 2)        //TODO: Check this Process for random number, it seems wrong!!!!
			{
				//double randomize = rand();
				double random_number = (double)rand() / RAND_MAX;
				if (random_number < (cur_population[ch_counter].maped_ff / maped_ff_Summation))
						Parents.push_back(cur_population[ch_counter]);
				ch_counter++;
				if (ch_counter == c_Pop_Size -1)
					ch_counter = 0;
			}

			double random_number_1 = (double)rand() / RAND_MAX;
			if (random_number_1 < c_Crossover_Rate)
			{
				Cross_Over (Parents[0], Parents[1], c_Number_of_Cuts, Child_One, Child_Two);

				//Child_One ff Calculation
				Chrom_to_Chosen_Paths (Child_One, Chosen_Paths, All_Feasible_Paths);
				
				WT_EH_Dir_OF001_Calculation (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map, Total_Waiting_Time, Car_Shortest_Path, Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path, Passing_Routes, Node_Route_Index, Transfer_Points, Transfer_Matrix, Covered_Demand_0_transfer, Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, Total_Demand, Total_Fleet_Size);

				Child_One.WT = Total_Waiting_Time;
				Child_One.EH = Total_Empty_Seat_Minutes;
				Child_One.Dir = Total_Directness_from_Shorthest_Path;
				Child_One.FS = Total_Fleet_Size;
				Child_One.UNCOVD = Un_Covered_Demand;
				
				Child_One.ff = C1 * Child_One.WT + C2 * Child_One.EH + C3 * Child_One.Dir + C4 * Child_One.FS + C5 * Child_One.UNCOVD;

				Child_One.ff_Condition = true;

				//Child_Two ff Calculation
				Chrom_to_Chosen_Paths (Child_Two, Chosen_Paths, All_Feasible_Paths);
				
				WT_EH_Dir_OF001_Calculation (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map, Total_Waiting_Time, Car_Shortest_Path, Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path, Passing_Routes, Node_Route_Index, Transfer_Points, Transfer_Matrix, Covered_Demand_0_transfer, Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, Total_Demand, Total_Fleet_Size);

				Child_Two.WT = Total_Waiting_Time;
				Child_Two.EH = Total_Empty_Seat_Minutes;
				Child_Two.Dir = Total_Directness_from_Shorthest_Path;
				Child_Two.FS = Total_Fleet_Size;
				Child_Two.UNCOVD = Un_Covered_Demand;
				
				Child_Two.ff = C1 * Child_Two.WT + C2 * Child_Two.EH + C3 * Child_Two.Dir +	C4 * Child_Two.FS + C5 * Child_Two.UNCOVD;

				Child_Two.ff_Condition = true;

				if ((Child_One.ff < Parents[0].ff) || (Child_One.ff < Parents[1].ff) || (Child_Two.ff < Parents[0].ff) ||(Child_Two.ff < Parents[1].ff))
				{
					temp_Population.push_back(Child_One);
					temp_Population.push_back(Child_Two);
				}
				else
				{
					temp_Population.push_back(Parents[0]);
					temp_Population.push_back(Parents[1]);
				}
			}
			else
			{
				temp_Population.push_back(Parents[0]);
				temp_Population.push_back(Parents[1]);
			}
		}
		cout << "Generation No.  " << Gen_counter << endl;
		cout << "Best Chromosome ff: " << cur_population[0].ff <<endl;
//-----Mutation---------------
		int number_of_mutated_genes = (int)(Chromosome_Length * c_Pop_Size * c_Mutation_Rate);
		for (unsigned i = 0; i < number_of_mutated_genes; i++)
		{
			int xrand = (rand() * (Chromosome_Length * c_Pop_Size)) / RAND_MAX;
			int chno = (int)(xrand / c_Pop_Size);
			int genno = xrand % Chromosome_Length;
			assert(chno < c_Pop_Size);
			assert(genno < Chromosome_Length);

			if (chno > 1)      //not mutate the best ones
				if (temp_Population[chno].Chrom[genno] == true)
				{
					temp_Population[chno].Chrom[genno] = false;
					
					temp_Population[chno].ff_Condition = false;
					
					Chrom_to_Chosen_Paths (temp_Population[chno], Chosen_Paths, All_Feasible_Paths);
					WT_EH_Dir_OF001_Calculation (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map, Total_Waiting_Time, Car_Shortest_Path, Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path, Passing_Routes, Node_Route_Index, Transfer_Points, Transfer_Matrix, Covered_Demand_0_transfer, Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, Total_Demand, Total_Fleet_Size);

					temp_Population[chno].WT = Total_Waiting_Time;
					temp_Population[chno].EH = Total_Empty_Seat_Minutes;
					temp_Population[chno].Dir = Total_Directness_from_Shorthest_Path;
					temp_Population[chno].FS = Total_Fleet_Size;
					temp_Population[chno].UNCOVD = Un_Covered_Demand;
					
					temp_Population[chno].ff = C1 * temp_Population[chno].WT + C2 * temp_Population[chno].EH + C3 * temp_Population[chno].Dir +	C4 * temp_Population[chno].FS + C5 * temp_Population[chno].UNCOVD;

					temp_Population[chno].ff_Condition = true;
				}
				else
				{
					temp_Population[chno].Chrom[genno] = true;

					temp_Population[chno].ff_Condition = false;
					
					Chrom_to_Chosen_Paths (temp_Population[chno], Chosen_Paths, All_Feasible_Paths);
					WT_EH_Dir_OF001_Calculation (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map, Total_Waiting_Time, Car_Shortest_Path, Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path, Passing_Routes, Node_Route_Index, Transfer_Points, Transfer_Matrix, Covered_Demand_0_transfer, Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, Total_Demand, Total_Fleet_Size);

					temp_Population[chno].WT = Total_Waiting_Time;
					temp_Population[chno].EH = Total_Empty_Seat_Minutes;
					temp_Population[chno].Dir = Total_Directness_from_Shorthest_Path;
					temp_Population[chno].FS = Total_Fleet_Size;
					temp_Population[chno].UNCOVD = Un_Covered_Demand;
					
					temp_Population[chno].ff = C1 * temp_Population[chno].WT + C2 * temp_Population[chno].EH + C3 * temp_Population[chno].Dir +	C4 * temp_Population[chno].FS + C5 * temp_Population[chno].UNCOVD;

					temp_Population[chno].ff_Condition = true;

				}
		}
		
		
		
		
		cur_population = temp_Population;
		//swap (cur_population, temp_Population);
		Gen_counter++;
	}
}

#endif
//------------------------------------------------------------------------
//==================================================================================================================
//==================================================================================================================
//==================================================================================================================
