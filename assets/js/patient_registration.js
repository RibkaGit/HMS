document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('date_of_birth');
    const ageYInput = document.getElementById('age_y');
    const ageMInput = document.getElementById('age_m');
    const ageDInput = document.getElementById('age_d');
    
    const patientAddressVillage = document.getElementById('address_village');
    const patientProvince = document.getElementById('province');
    const patientDistrict = document.getElementById('district_khan');
    const patientCommune = document.getElementById('commune_sangkat');
    const patientPostalCode = document.getElementById('postal_code');
    const patientTelephone2 = document.getElementById('telephone_2');
    
    const relativeSameAddress = document.getElementById('relative_same_address');
    const relativeAddressVillage = document.getElementById('relative_address_village');
    const relativeProvince = document.getElementById('relative_province');
    const relativeDistrict = document.getElementById('relative_district_khan');
    const relativeCommune = document.getElementById('relative_commune_sangkat');
    const relativePostalCode = document.getElementById('relative_postal_code');
    const relativeTelephone2 = document.getElementById('relative_telephone_2');
    
    // Function to calculate Age (Years, Months, Days)
    function updateAge() {
        const dobVal = dobInput.value;
        if (!dobVal) {
            ageYInput.value = '';
            ageMInput.value = '';
            ageDInput.value = '';
            return;
        }
        
        const dob = new Date(dobVal);
        const today = new Date();
        
        if (isNaN(dob.getTime())) return;
        
        let years = today.getFullYear() - dob.getFullYear();
        let months = today.getMonth() - dob.getMonth();
        let days = today.getDate() - dob.getDate();
        
        if (days < 0) {
            months -= 1;
            // Days in previous month
            const prevMonthDate = new Date(today.getFullYear(), today.getMonth(), 0);
            days += prevMonthDate.getDate();
        }
        
        if (months < 0) {
            years -= 1;
            months += 12;
        }
        
        ageYInput.value = years >= 0 ? years : 0;
        ageMInput.value = months >= 0 ? months : 0;
        ageDInput.value = days >= 0 ? days : 0;
    }
    
    // Function to copy patient address to relative address
    function handleAddressSync() {
        if (relativeSameAddress.checked) {
            relativeAddressVillage.value = patientAddressVillage.value;
            relativeProvince.value = patientProvince.value;
            relativeDistrict.value = patientDistrict.value;
            relativeCommune.value = patientCommune.value;
            relativePostalCode.value = patientPostalCode.value;
            relativeTelephone2.value = patientTelephone2.value;
            
            // Add readonly to relative inputs while synced
            [relativeAddressVillage, relativeProvince, relativeDistrict, relativeCommune, relativePostalCode, relativeTelephone2].forEach(el => {
                el.setAttribute('readonly', 'true');
                el.style.backgroundColor = '#f8fafc';
            });
        } else {
            // Remove readonly and clear style
            [relativeAddressVillage, relativeProvince, relativeDistrict, relativeCommune, relativePostalCode, relativeTelephone2].forEach(el => {
                el.removeAttribute('readonly');
                el.style.backgroundColor = '';
            });
        }
    }
    
    // Attach event listeners
    if (dobInput) {
        dobInput.addEventListener('change', updateAge);
        // Run initial calculation if DOB is already set (e.g. edit mode)
        if (dobInput.value) {
            updateAge();
        }
    }
    
    if (relativeSameAddress) {
        relativeSameAddress.addEventListener('change', handleAddressSync);
        
        // Listen to changes in patient address fields to sync in real-time
        const patientFields = [patientAddressVillage, patientProvince, patientDistrict, patientCommune, patientPostalCode, patientTelephone2];
        patientFields.forEach(field => {
            if (field) {
                field.addEventListener('input', function() {
                    if (relativeSameAddress.checked) {
                        handleAddressSync();
                    }
                });
                field.addEventListener('change', function() {
                    if (relativeSameAddress.checked) {
                        handleAddressSync();
                    }
                });
            }
        });
        
        // Run once on load to establish state (e.g., if checked in edit mode)
        handleAddressSync();
    }
});
