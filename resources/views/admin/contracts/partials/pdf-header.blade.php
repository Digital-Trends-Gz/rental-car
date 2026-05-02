<table class="header-table">
    <tr>
        <td class="header-left">
            <div>{{ $headerCountryEn }}</div>
            <div>C.R : {{ $headerCrNumber }}</div>
            <div>P.O Box : {{ $headerPoBox }}</div>
            <div>P.C : {{ $headerPc }}</div>
            <div>GSM : {{ $headerGsm1 }}</div>
            <div>GSM : {{ $headerGsm2 }}</div>
            <div>GSM : {{ $headerGsm3 }}</div>
            <div class="serial-no">{{ $headerCountryEn }} {{ $contract->contract_number }}</div>
        </td>
        <td class="header-center">
            @if(!empty($companyLogo))
                <img src="{{ $companyLogo }}" class="company-logo" alt="Logo" />
            @endif
            <div class="company-name-en">{{ strtoupper($headerCompanyNameEn) }}</div>
            <div class="company-name-ar ar center-name">{{ $headerCompanyNameAr }}</div>
            <div class="contract-title-row">
                <div class="contract-title-en">CAR RENTAL CONTRACT</div>
                <div class="contract-title-ar ar">عقد إيجار سيارة</div>
            </div>
        </td>
        <td class="header-right ar">
            <div>{{ $headerCountryAr }}</div>
            <div>رقم السجل التجاري: {{ $headerCrNumber }}</div>
            <div>ص.ب: {{ $headerPoBox }}</div>
            <div>الرمز البريدي: {{ $headerPc }}</div>
            <div>نقال: {{ $headerGsm1 }}</div>
            <div>نقال: {{ $headerGsm2 }}</div>
            <div>نقال: {{ $headerGsm3 }}</div>
        </td>
    </tr>
</table>
