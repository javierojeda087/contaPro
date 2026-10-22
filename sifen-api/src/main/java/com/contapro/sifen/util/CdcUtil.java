package com.contapro.sifen.util;

import java.security.SecureRandom;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Utilidad oficial para generación, validación (Módulo 11 DNIT) y decodificación
 * del Código de Control Documental (CDC de 44 dígitos) según el Manual Técnico SIFEN v150.
 */
public class CdcUtil {

    private static final SecureRandom RANDOM = new SecureRandom();

    /**
     * Genera un código de seguridad aleatorio de 9 dígitos (no empieza con 0).
     */
    public static String generarCodigoSeguridad() {
        int num = 100000000 + RANDOM.nextInt(900000000);
        return String.valueOf(num);
    }

    /**
     * Calcula el Dígito Verificador (Módulo 11 base 11 de la DNIT Paraguay).
     */
    public static int calcularDigitoVerificador(String numero, int baseMax) {
        int k = 2;
        int total = 0;
        for (int i = numero.length() - 1; i >= 0; i--) {
            char c = numero.charAt(i);
            int n;
            if (Character.isDigit(c)) {
                n = c - '0';
            } else {
                n = (int) Character.toUpperCase(c);
            }
            if (k > baseMax) {
                k = 2;
            }
            total += n * k;
            k++;
        }
        int resto = total % 11;
        return resto > 1 ? 11 - resto : 0;
    }

    public static int calcularDvMod11(String numero) {
        return calcularDigitoVerificador(numero, 11);
    }

    /**
     * Separa RUC y DV. Si el RUC no trae guion, calcula su DV automáticamente con Módulo 11.
     */
    public static String[] separarRucDv(String rucCompleto) {
        if (rucCompleto == null || rucCompleto.isBlank()) {
            return new String[]{"80000000", "0"};
        }
        String clean = rucCompleto.trim().replaceAll("[^0-9A-Za-z-]", "");
        if (clean.contains("-")) {
            String[] parts = clean.split("-", 2);
            String base = parts[0].replaceAll("\\D", "");
            String dv = parts[1].replaceAll("\\D", "");
            if (dv.isEmpty()) {
                dv = String.valueOf(calcularDvMod11(base));
            } else if (dv.length() > 1) {
                dv = dv.substring(0, 1);
            }
            return new String[]{base, dv};
        } else {
            String base = clean.replaceAll("\\D", "");
            String dv = String.valueOf(calcularDvMod11(base));
            return new String[]{base, dv};
        }
    }

    /**
     * Genera el CDC completo de 44 dígitos conforme a la estructura SIFEN v150:
     * [0-1]   iTiDE     (2 dígitos)
     * [2-9]   dRucEm    (8 dígitos, completado con ceros a la izquierda)
     * [10]    dDVEmi    (1 dígito)
     * [11-13] dEst      (3 dígitos)
     * [14-16] dPunExp   (3 dígitos)
     * [17-23] dNumDoc   (7 dígitos)
     * [24]    iTipCont  (1 dígito: 1=Física, 2=Jurídica)
     * [25-32] dFeEmiDE  (8 dígitos: YYYYMMDD)
     * [33]    iTipEmi   (1 dígito: 1=Normal, 2=Contingencia)
     * [34-42] dCodSeg   (9 dígitos)
     * [43]    dDVId     (1 dígito verificador Módulo 11)
     */
    public static String generarCdc(
            int tipoDocumento,
            String rucBase,
            String dvEmisor,
            String establecimiento,
            String puntoExpedicion,
            String numeroDocumento,
            int tipoContribuyente,
            String fechaEmisionYmd,
            int tipoEmision,
            String codigoSeguridad
    ) {
        String tiDe = String.format("%02d", tipoDocumento);
        String rucPad = leftPadDigits(rucBase, 8);
        String dvPad = leftPadDigits(dvEmisor, 1);
        String estPad = leftPadDigits(establecimiento, 3);
        String punPad = leftPadDigits(puntoExpedicion, 3);
        String numPad = leftPadDigits(numeroDocumento, 7);
        String tipCont = String.valueOf(tipoContribuyente == 2 ? 2 : 1);
        String fecClean = fechaEmisionYmd.replaceAll("\\D", "");
        if (fecClean.length() >= 8) {
            fecClean = fecClean.substring(0, 8);
        } else {
            fecClean = "20260101";
        }
        String tipEmi = String.valueOf(tipoEmision == 2 ? 2 : 1);
        String codSeg = leftPadDigits(codigoSeguridad, 9);

        String base43 = tiDe + rucPad + dvPad + estPad + punPad + numPad + tipCont + fecClean + tipEmi + codSeg;
        int dvCdc = calcularDvMod11(base43);
        return base43 + dvCdc;
    }

    /**
     * Formatea el CDC de 44 dígitos en bloques de 4 separados por espacio para el KuDE.
     */
    public static String formatearCdcParaKude(String cdc) {
        if (cdc == null) return "";
        String clean = cdc.replaceAll("\\D", "");
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < clean.length(); i++) {
            if (i > 0 && i % 4 == 0) sb.append(' ');
            sb.append(clean.charAt(i));
        }
        return sb.toString();
    }

    /**
     * Decodifica y valida cualquier CDC de 44 dígitos (extrae RUC, Factura, Fecha, Tipo Doc, etc.).
     */
    public static Map<String, Object> decodificarCdc(String cdcRaw) {
        Map<String, Object> res = new LinkedHashMap<>();
        String cdc = cdcRaw != null ? cdcRaw.replaceAll("\\D", "") : "";
        res.put("cdc", cdc);

        if (cdc.length() != 44) {
            res.put("valido", false);
            res.put("mensaje", "El CDC debe contener exactamente 44 dígitos numéricos (recibido: " + cdc.length() + ").");
            return res;
        }

        String base43 = cdc.substring(0, 43);
        int dvRecibido = Character.getNumericValue(cdc.charAt(43));
        int dvCalculado = calcularDvMod11(base43);
        boolean dvValido = (dvRecibido == dvCalculado);

        String codTipoDoc = cdc.substring(0, 2);
        String rucRaw = cdc.substring(2, 10);
        String rucSinCeros = rucRaw.replaceFirst("^0+(?!$)", "");
        String dvEmisor = cdc.substring(10, 11);
        String est = cdc.substring(11, 14);
        String pto = cdc.substring(14, 17);
        String numDoc = cdc.substring(17, 24);
        String tipCont = cdc.substring(24, 25);
        String fecRaw = cdc.substring(25, 33); // YYYYMMDD
        String tipEmi = cdc.substring(33, 34);
        String codSeg = cdc.substring(34, 43);

        String fechaIso = fecRaw.substring(0, 4) + "-" + fecRaw.substring(4, 6) + "-" + fecRaw.substring(6, 8);
        String tipoDocNombre = switch (codTipoDoc) {
            case "01" -> "Factura";
            case "04" -> "Autofactura";
            case "05" -> "Nota de Crédito";
            case "06" -> "Nota de Débito";
            case "07" -> "Nota de Remisión";
            default -> "Factura";
        };

        res.put("valido", dvValido);
        res.put("dvRecibido", dvRecibido);
        res.put("dvCalculado", dvCalculado);
        res.put("cdcFormateado", formatearCdcParaKude(cdc));
        res.put("codigoTipoDocumento", Integer.parseInt(codTipoDoc));
        res.put("tipoDocumento", tipoDocNombre);
        res.put("tipoTimbrado", "Electrónico");
        res.put("rucEmisor", rucSinCeros + "-" + dvEmisor);
        res.put("rucBase", rucSinCeros);
        res.put("dvEmisor", dvEmisor);
        res.put("establecimiento", est);
        res.put("puntoExpedicion", pto);
        res.put("numeroDocumento", numDoc);
        res.put("nroFactura", est + "-" + pto + "-" + numDoc);
        res.put("tipoContribuyente", "2".equals(tipCont) ? "Persona Jurídica" : "Persona Física");
        res.put("fechaEmision", fechaIso);
        res.put("tipoEmision", "2".equals(tipEmi) ? "Contingencia" : "Normal");
        res.put("codigoSeguridad", codSeg);
        res.put("mensaje", dvValido
                ? "CDC válido de 44 dígitos (Dígito Verificador Módulo 11 correcto)."
                : "Advertencia: El dígito verificador del CDC (" + dvRecibido + ") no coincide con el Módulo 11 calculado (" + dvCalculado + ").");

        return res;
    }

    public static String leftPadDigits(String val, int length) {
        String digits = val != null ? val.replaceAll("\\D", "") : "";
        if (digits.length() > length) {
            digits = digits.substring(digits.length() - length);
        }
        return String.format("%" + length + "s", digits).replace(' ', '0');
    }
}
