#include "functions.h" 

using namespace std;

const char c_OutputFileName [] = "RES011.txt";
const char c_Routes_Numbers [] = "routes.txt";

int main ()
{
	clock_t start_time, end_time;
	start_time = clock();

	//Reading the network 
	CityMap city (c_CityFileName);
	
	//Reading Demand
	
	ifstream fdemand (c_ODMatrixFileName);
	ofstream fout (c_OutputFileName);
	ofstream froute (c_Routes_Numbers);
	
	unsigned N;
	fdemand >> N;
	vector<DemandElement> ODvector;

	double tempo = -1;
	DemandElement xtemp;
	vector<vector<double> > ODmatrix (N, vector<double> (N, c_NoEdge));
	double total_demand = 0;

	for (unsigned i = 0; i < N; i++)
		for (unsigned j = 0; j < N; j++)
		{
			xtemp.from = i;
			xtemp.to = j;
			fdemand >> tempo;
			xtemp.demand = tempo;
			ODvector.push_back (xtemp);

			ODmatrix[i][j] = tempo;
			total_demand += tempo;
		}	


		vector<PathT> Chosen_Paths;

		vector<vector<Index_Route_Pair> > Passing_Routes;
		vector<vector<int> > Node_Route_Index;

		vector<vector<Node_Route_Pair> > Transfer_Points;
		vector<vector<vector<int> > > Transfer_Matrix;

		vector<vector<vector<Possible_Strategies> > > Possible_Paths;
		
		vector<vector<vector<double> > > Network_Flow;
		double Covered_Demand_0_transfer;
		double Covered_Demand_1_transfer;
		vector<vector<double> > Bus_Shortest_Path;
		vector<vector<vector<Assignment_Map_Element> > > Assignment_Map;

		double Un_Covered_Demand;

		vector<vector<double> > Car_Shortest_Path;

		double Total_Waiting_Time;
		double Total_Empty_Seat_Minutes; 
		double Total_Directness_from_Shorthest_Path;

		double Total_Fleet_Size = 0;

		vector<PathT> All_Feasible_Paths;
	RGA001_Route_Generation_Algorithm (city, Car_Shortest_Path, All_Feasible_Paths);

	vector<Chromosome_Type_001> GA001_Results;
	GA001 (ODmatrix, Possible_Paths, Chosen_Paths, Network_Flow, city, Assignment_Map, Total_Waiting_Time, Car_Shortest_Path,
		Total_Empty_Seat_Minutes, Total_Directness_from_Shorthest_Path, Passing_Routes, Node_Route_Index, Transfer_Points,
		Transfer_Matrix, Covered_Demand_0_transfer, Covered_Demand_1_transfer, Un_Covered_Demand, Bus_Shortest_Path, total_demand,
		All_Feasible_Paths, Total_Fleet_Size, GA001_Results);
	
	
	//Printing Routes
	if (Print_routes == true)
	{
		for (unsigned i = 0; i < All_Feasible_Paths.size(); i++)
		{	froute << "Route No:" << "," << i << "nodes";
			for (unsigned j = 0; j < All_Feasible_Paths[i].path.size(); j++)
				froute << "," << All_Feasible_Paths[i].path[j];
			froute << endl;
		}
	}


	end_time = clock();
	double Running_Time = ((double) (end_time - start_time) / (double) (CLOCKS_PER_SEC));


	//Printing output file
	fout << "-----------Common Constants" << endl; 
	fout << "c_ShortestPathDeviationCar : " << c_ShortestPathDeviationCar << endl; 
	fout << "c_DemandCoverageMinimum : " << c_DemandCoverageMinimum << endl; 
	fout << "c_BusCapacity : " << c_BusCapacity << endl; 
	fout << "c_Desirable_Capacity_Portion : " << c_Desirable_Capacity_Portion << endl; 
	fout << "c_MinimumFrequency : " << c_MinimumFrequency << endl; 
	fout << "c_TransferPenalty(In Minutes) :" << c_TransferPenalty << endl; 
	fout << "c_Deviation_for_Elimination : " << c_Deviation_for_Elimination << endl; 

	fout << "-----------Objective Function commons : " << endl; 
	fout << "Waiting time value for an hour (C1) : " << C1 << endl; 
	fout << "Empty seat hrs value for an hour (C2) : " << C2 << endl; 
	fout << "Direcness value for an hour (C3) : " << C3 << endl; 
	fout << "Fleet Size value (C4) : " << C4 << endl; 
	fout << "Uncovered Demand value (C5) : " << C5 << endl; 

	fout << "----------Genetic Algorithm" << endl; 
	fout << "c_Crossover_Rate : " << c_Crossover_Rate << endl; 
	fout << "c_Mutation_Rate : " << c_Mutation_Rate << endl; 
	fout << "c_Pop_Size : " << c_Pop_Size << endl; 
	fout << "c_MAX_Allowable_Generations : " << c_MAX_Allowable_Generations << endl; 
	fout << "c_Best_Chroms_Survival_Rate : " << c_Best_Chroms_Survival_Rate << endl; 
	fout << "Recent_Count : " << Recent_Count << endl; 
	fout << "RECENT_LIMIT : " << RECENT_LIMIT << endl; 
	fout << "Select_Gene_Probability : " << Select_Gene_Probability << endl; 
	fout << "c_Number_of_Cuts : " << c_Number_of_Cuts << endl; 

	fout << "---------RGA constants" << endl; 
	fout << "Min_Route_Length(In Minutes) : " << Min_Route_Length << endl; 
	fout << "Max_Route_Length(In Minutes) : " << Max_Route_Length << endl; 
	fout << "Deviation_from_SP_RGA : " << Deviation_from_SP_RGA << endl; 

	fout << "Running time: " << Running_Time << endl; 
	fout << "-------------------------------------------------------------" <<endl;


	for (unsigned i = 0; i < GA001_Results.size(); i++)
	{
		fout << "Gen" << i << "," << GA001_Results[i].ff << "," << GA001_Results[i].WT << "," << GA001_Results[i].EH << "," << GA001_Results[i].Dir  << "," << GA001_Results[i].FS  << "," << (double)GA001_Results[i].UNCOVD / total_demand << "," << "Paths";
		for (unsigned j = 0; j < GA001_Results[i].Chrom.size(); j++)
			if (GA001_Results[i].Chrom[j] == true)
				fout << "," << j;
		fout << endl;
	}
	return 0;
}
