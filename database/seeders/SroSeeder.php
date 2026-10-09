<?php

namespace Database\Seeders;

use App\Models\Sro;
use Illuminate\Database\Seeder;

/** Sample Sub-Registrar Offices for Tamil Nadu districts (editable in Prerequisites → SRO). */
class SroSeeder extends Seeder
{
    public function run(): void
    {
        if (Sro::exists()) {
            return;
        }
        $rows = [
            ['Thanjavur', 'Thanjavur', 'SRO Thanjavur (Joint I)'], ['Thanjavur', 'Thanjavur', 'SRO Thanjavur (Joint II)'], ['Thanjavur', 'Vallam', 'SRO Vallam'],
            ['Thanjavur', 'Kumbakonam', 'SRO Kumbakonam'], ['Thanjavur', 'Pattukkottai', 'SRO Pattukkottai'], ['Thanjavur', 'Orathanadu', 'SRO Orathanadu'], ['Thanjavur', 'Papanasam', 'SRO Papanasam'],
            ['Tiruchirappalli', 'Tiruchirappalli', 'SRO Tiruchirappalli (Joint I)'], ['Tiruchirappalli', 'Srirangam', 'SRO Srirangam'], ['Tiruchirappalli', 'Lalgudi', 'SRO Lalgudi'],
            ['Pudukkottai', 'Pudukkottai', 'SRO Pudukkottai'], ['Pudukkottai', 'Gandarvakottai', 'SRO Gandarvakottai'], ['Pudukkottai', 'Aranthangi', 'SRO Aranthangi'],
            ['Tiruvarur', 'Tiruvarur', 'SRO Tiruvarur'], ['Tiruvarur', 'Mannargudi', 'SRO Mannargudi'], ['Nagapattinam', 'Nagapattinam', 'SRO Nagapattinam'],
            ['Mayiladuthurai', 'Mayiladuthurai', 'SRO Mayiladuthurai'], ['Ariyalur', 'Ariyalur', 'SRO Ariyalur'], ['Perambalur', 'Perambalur', 'SRO Perambalur'],
            ['Madurai', 'Madurai North', 'SRO Madurai North'], ['Madurai', 'Madurai South', 'SRO Madurai South'], ['Madurai', 'Thirumangalam', 'SRO Thirumangalam'],
            ['Coimbatore', 'Coimbatore North', 'SRO Gandhipuram'], ['Coimbatore', 'Coimbatore South', 'SRO Singanallur'], ['Coimbatore', 'Pollachi', 'SRO Pollachi'],
            ['Chennai', 'Egmore', 'SRO Periamet'], ['Chennai', 'Mylapore', 'SRO Mylapore'], ['Chengalpattu', 'Tambaram', 'SRO Tambaram'], ['Chengalpattu', 'Chengalpattu', 'SRO Chengalpattu'],
            ['Kancheepuram', 'Kancheepuram', 'SRO Kancheepuram'], ['Tiruvallur', 'Tiruvallur', 'SRO Tiruvallur'], ['Salem', 'Salem', 'SRO Salem (Joint I)'], ['Salem', 'Attur', 'SRO Attur'],
            ['Erode', 'Erode', 'SRO Erode'], ['Tiruppur', 'Tiruppur', 'SRO Tiruppur'], ['Vellore', 'Vellore', 'SRO Vellore'], ['Tirunelveli', 'Palayamkottai', 'SRO Palayamkottai'],
            ['Thoothukudi', 'Thoothukudi', 'SRO Thoothukudi'], ['Kanniyakumari', 'Nagercoil', 'SRO Nagercoil'], ['Dindigul', 'Dindigul', 'SRO Dindigul'], ['Karur', 'Karur', 'SRO Karur'],
            ['Namakkal', 'Namakkal', 'SRO Namakkal'], ['Villupuram', 'Villupuram', 'SRO Villupuram'], ['Cuddalore', 'Cuddalore', 'SRO Cuddalore'], ['Sivaganga', 'Karaikudi', 'SRO Karaikudi'],
            ['Ramanathapuram', 'Ramanathapuram', 'SRO Ramanathapuram'], ['Virudhunagar', 'Sivakasi', 'SRO Sivakasi'], ['Theni', 'Theni', 'SRO Theni'], ['Krishnagiri', 'Hosur', 'SRO Hosur'],
            ['Dharmapuri', 'Dharmapuri', 'SRO Dharmapuri'], ['Tiruvannamalai', 'Tiruvannamalai', 'SRO Tiruvannamalai'], ['Kallakurichi', 'Kallakurichi', 'SRO Kallakurichi'],
            ['Ranipet', 'Ranipet', 'SRO Ranipet'], ['Tirupathur', 'Tirupathur', 'SRO Tirupathur'], ['Tenkasi', 'Tenkasi', 'SRO Tenkasi'], ['The Nilgiris', 'Udhagamandalam', 'SRO Ooty'],
        ];
        foreach ($rows as [$district, $taluk, $name]) {
            Sro::create(['state' => 'Tamil Nadu', 'district' => $district, 'taluk' => $taluk, 'name' => $name]);
        }
    }
}
