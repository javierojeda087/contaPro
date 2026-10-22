package com.contapro.sifen.util;

import com.contapro.sifen.dto.EmitirDeRequest;
import com.contapro.sifen.dto.EventoSifenRequest;
import com.contapro.sifen.dto.ItemDeDto;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

/**
 * Constructor oficial de XML SIFEN v150 (<rDE> y <rEnviEventoDe>) conforme al Manual Técnico v150 de la DNIT.
 */
public class SifenXmlBuilder {

    public static class BuiltDe {
        public final String cdc;
        public final String codigoSeguridad;
        public final String fechaEmisionIso;
        public final String rucReceptorSinDv;
        public final boolean receptorContribuyente;
        public final String numDocReceptor;
        public final int cantidadItems;
        public final BigDecimal subExentas;
        public final BigDecimal subGrav5;
        public final BigDecimal subGrav10;
        public final BigDecimal iva5;
        public final BigDecimal iva10;
        public final BigDecimal totalIva;
        public final BigDecimal totalGeneral;
        public final String xmlUnsigned;

        public BuiltDe(String cdc, String codigoSeguridad, String fechaEmisionIso,
                       String rucReceptorSinDv, boolean receptorContribuyente, String numDocReceptor,
                       int cantidadItems, BigDecimal subExentas, BigDecimal subGrav5, BigDecimal subGrav10,
                       BigDecimal iva5, BigDecimal iva10, BigDecimal totalIva, BigDecimal totalGeneral,
                       String xmlUnsigned) {
            this.cdc = cdc;
            this.codigoSeguridad = codigoSeguridad;
            this.fechaEmisionIso = fechaEmisionIso;
            this.rucReceptorSinDv = rucReceptorSinDv;
            this.receptorContribuyente = receptorContribuyente;
            this.numDocReceptor = numDocReceptor;
            this.cantidadItems = cantidadItems;
            this.subExentas = subExentas;
            this.subGrav5 = subGrav5;
            this.subGrav10 = subGrav10;
            this.iva5 = iva5;
            this.iva10 = iva10;
            this.totalIva = totalIva;
            this.totalGeneral = totalGeneral;
            this.xmlUnsigned = xmlUnsigned;
        }
    }

    public static BuiltDe construirDeXml(EmitirDeRequest req) {
        int tipoDoc = req.getTipoDocumento() != null ? req.getTipoDocumento() : 1;
        String descTipoDoc = switch (tipoDoc) {
            case 4 -> "Autofactura electrónica";
            case 5 -> "Nota de crédito electrónica";
            case 6 -> "Nota de débito electrónica";
            case 7 -> "Nota de remisión electrónica";
            default -> "Factura electrónica";
        };

        String[] rucDvEmisor = CdcUtil.separarRucDv(req.getRucEmisor());
        String rucEmisorBase = rucDvEmisor[0];
        String dvEmisor = rucDvEmisor[1];

        String est = CdcUtil.leftPadDigits(req.getEstablecimiento() != null ? req.getEstablecimiento() : "001", 3);
        String punExp = CdcUtil.leftPadDigits(req.getPuntoExpedicion() != null ? req.getPuntoExpedicion() : "001", 3);
        String numDoc = CdcUtil.leftPadDigits(req.getNumeroDocumento() != null ? req.getNumeroDocumento() : "0000001", 7);
        int tipCont = (req.getTipoContribuyente() != null && req.getTipoContribuyente() == 2)
                || rucEmisorBase.startsWith("80") ? 2 : 1;
        int tipEmi = (req.getTipoEmision() != null && req.getTipoEmision() == 2) ? 2 : 1;

        String rawFecha = req.getFechaEmision();
        String fechaEmisionIso;
        if (rawFecha == null || rawFecha.isBlank()) {
            fechaEmisionIso = LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ss"));
        } else if (rawFecha.trim().length() == 10) {
            String horaActual = LocalDateTime.now().format(DateTimeFormatter.ofPattern("HH:mm:ss"));
            fechaEmisionIso = rawFecha.trim() + "T" + horaActual;
        } else {
            fechaEmisionIso = rawFecha.trim();
        }
        String fechaYmd = fechaEmisionIso.substring(0, 10).replaceAll("-", "");

        String codSeg = CdcUtil.generarCodigoSeguridad();
        String cdc = CdcUtil.generarCdc(
                tipoDoc, rucEmisorBase, dvEmisor, est, punExp, numDoc, tipCont, fechaYmd, tipEmi, codSeg
        );
        String dvId = cdc.substring(43);

        //Preparar lista de ítems (si no viene detalle, sintetizar desde grav10, grav5 y exentas)
        List<ItemDeDto> items = new ArrayList<>();
        if (req.getItems() != null && !req.getItems().isEmpty()) {
            items.addAll(req.getItems());
        } else {
            String obsBase = (req.getObservacion() != null && !req.getObservacion().isBlank())
                    ? req.getObservacion().trim()
                    : "VENTA DE MERCADERÍAS / SERVICIOS";
            if (req.getGrav10() != null && req.getGrav10().compareTo(BigDecimal.ZERO) > 0) {
                ItemDeDto i10 = new ItemDeDto();
                i10.setCodigo("001");
                i10.setDescripcion(obsBase + " (GRAV. 10%)");
                i10.setCantidad(BigDecimal.ONE);
                i10.setPrecioUnitario(req.getGrav10());
                i10.setTasaIva(10);
                items.add(i10);
            }
            if (req.getGrav5() != null && req.getGrav5().compareTo(BigDecimal.ZERO) > 0) {
                ItemDeDto i5 = new ItemDeDto();
                i5.setCodigo("002");
                i5.setDescripcion(obsBase + " (GRAV. 5%)");
                i5.setCantidad(BigDecimal.ONE);
                i5.setPrecioUnitario(req.getGrav5());
                i5.setTasaIva(5);
                items.add(i5);
            }
            if (req.getExentas() != null && req.getExentas().compareTo(BigDecimal.ZERO) > 0) {
                ItemDeDto iEx = new ItemDeDto();
                iEx.setCodigo("003");
                iEx.setDescripcion(obsBase + " (EXENTA)");
                iEx.setCantidad(BigDecimal.ONE);
                iEx.setPrecioUnitario(req.getExentas());
                iEx.setTasaIva(0);
                items.add(iEx);
            }
            if (items.isEmpty()) {
                ItemDeDto iDef = new ItemDeDto();
                iDef.setCodigo("001");
                iDef.setDescripcion(obsBase);
                iDef.setCantidad(BigDecimal.ONE);
                iDef.setPrecioUnitario(BigDecimal.ZERO);
                iDef.setTasaIva(10);
                items.add(iDef);
            }
        }

        // Datos del Receptor
        String rawRucCli = req.getRucCliente() != null ? req.getRucCliente().trim() : "";
        boolean esContribuyente = rawRucCli.contains("-") || rawRucCli.startsWith("80");
        String[] rucDvCli = CdcUtil.separarRucDv(rawRucCli.isEmpty() ? "44444401-7" : rawRucCli);
        String rucCliBase = rucDvCli[0];
        String dvCli = rucDvCli[1];
        String nomCliente = (req.getRazonSocialCliente() != null && !req.getRazonSocialCliente().isBlank())
                ? req.getRazonSocialCliente().trim()
                : "SIN NOMBRE";

        // Totales e ítems XML
        BigDecimal subExe = BigDecimal.ZERO;
        BigDecimal sub5 = BigDecimal.ZERO;
        BigDecimal sub10 = BigDecimal.ZERO;
        BigDecimal totIva5 = BigDecimal.ZERO;
        BigDecimal totIva10 = BigDecimal.ZERO;
        BigDecimal baseGrav5 = BigDecimal.ZERO;
        BigDecimal baseGrav10 = BigDecimal.ZERO;

        StringBuilder itemsXml = new StringBuilder();
        int idx = 1;
        for (ItemDeDto item : items) {
            String cod = (item.getCodigo() != null && !item.getCodigo().isBlank()) ? item.getCodigo().trim() : String.format("%03d", idx);
            String desc = (item.getDescripcion() != null && !item.getDescripcion().isBlank()) ? item.getDescripcion().trim() : "ITEM " + idx;
            BigDecimal cant = item.getCantidad() != null && item.getCantidad().compareTo(BigDecimal.ZERO) > 0 ? item.getCantidad() : BigDecimal.ONE;
            BigDecimal precio = item.getPrecioUnitario() != null ? item.getPrecioUnitario().setScale(0, RoundingMode.HALF_UP) : BigDecimal.ZERO;
            BigDecimal descMonto = item.getDescuento() != null ? item.getDescuento().setScale(0, RoundingMode.HALF_UP) : BigDecimal.ZERO;
            BigDecimal totBruto = cant.multiply(precio).setScale(0, RoundingMode.HALF_UP);
            BigDecimal totOpeItem = totBruto.subtract(descMonto).max(BigDecimal.ZERO);

            int tasa = item.getTasaIva() != null ? item.getTasaIva() : 10;
            int iAfecIVA;
            String dDesAfecIVA;
            BigDecimal dPropIVA;
            BigDecimal dTasaIVA;
            BigDecimal dBasGravIVA;
            BigDecimal dLiqIVAItem;
            BigDecimal dBasExe;

            if (tasa == 10) {
                iAfecIVA = 1;
                dDesAfecIVA = "Gravado IVA";
                dPropIVA = new BigDecimal("100");
                dTasaIVA = new BigDecimal("10");
                dLiqIVAItem = totOpeItem.divide(new BigDecimal("11"), 0, RoundingMode.HALF_UP);
                dBasGravIVA = totOpeItem.subtract(dLiqIVAItem);
                dBasExe = BigDecimal.ZERO;
                sub10 = sub10.add(totOpeItem);
                totIva10 = totIva10.add(dLiqIVAItem);
                baseGrav10 = baseGrav10.add(dBasGravIVA);
            } else if (tasa == 5) {
                iAfecIVA = 1;
                dDesAfecIVA = "Gravado IVA";
                dPropIVA = new BigDecimal("100");
                dTasaIVA = new BigDecimal("5");
                dLiqIVAItem = totOpeItem.divide(new BigDecimal("21"), 0, RoundingMode.HALF_UP);
                dBasGravIVA = totOpeItem.subtract(dLiqIVAItem);
                dBasExe = BigDecimal.ZERO;
                sub5 = sub5.add(totOpeItem);
                totIva5 = totIva5.add(dLiqIVAItem);
                baseGrav5 = baseGrav5.add(dBasGravIVA);
            } else {
                iAfecIVA = 3;
                dDesAfecIVA = "Exento";
                dPropIVA = BigDecimal.ZERO;
                dTasaIVA = BigDecimal.ZERO;
                dBasGravIVA = BigDecimal.ZERO;
                dLiqIVAItem = BigDecimal.ZERO;
                dBasExe = BigDecimal.ZERO;
                subExe = subExe.add(totOpeItem);
            }

            itemsXml.append("      <gCamItem>\n")
                    .append("        <dCodInt>").append(esc(cod)).append("</dCodInt>\n")
                    .append("        <dDesProSer>").append(esc(desc)).append("</dDesProSer>\n")
                    .append("        <cUniMed>77</cUniMed>\n")
                    .append("        <dDesUniMed>UNI</dDesUniMed>\n")
                    .append("        <dCantProSer>").append(cant.stripTrailingZeros().toPlainString()).append("</dCantProSer>\n")
                    .append("        <gValorItem>\n")
                    .append("          <dPUniProSer>").append(precio.toPlainString()).append("</dPUniProSer>\n")
                    .append("          <dTotBruOpeItem>").append(totBruto.toPlainString()).append("</dTotBruOpeItem>\n")
                    .append("          <gValorRestaItem>\n")
                    .append("            <dDescItem>").append(descMonto.toPlainString()).append("</dDescItem>\n")
                    .append("            <dPorcDesIt>0</dPorcDesIt>\n")
                    .append("            <dDescGloItem>0</dDescGloItem>\n")
                    .append("            <dAntPreUniIt>0</dAntPreUniIt>\n")
                    .append("            <dAntGloPreUniIt>0</dAntGloPreUniIt>\n")
                    .append("            <dTotOpeItem>").append(totOpeItem.toPlainString()).append("</dTotOpeItem>\n")
                    .append("          </gValorRestaItem>\n")
                    .append("        </gValorItem>\n")
                    .append("        <gCamIVA>\n")
                    .append("          <iAfecIVA>").append(iAfecIVA).append("</iAfecIVA>\n")
                    .append("          <dDesAfecIVA>").append(dDesAfecIVA).append("</dDesAfecIVA>\n")
                    .append("          <dPropIVA>").append(dPropIVA.toPlainString()).append("</dPropIVA>\n")
                    .append("          <dTasaIVA>").append(dTasaIVA.toPlainString()).append("</dTasaIVA>\n")
                    .append("          <dBasGravIVA>").append(dBasGravIVA.toPlainString()).append("</dBasGravIVA>\n")
                    .append("          <dLiqIVAItem>").append(dLiqIVAItem.toPlainString()).append("</dLiqIVAItem>\n")
                    .append("          <dBasExe>").append(dBasExe.toPlainString()).append("</dBasExe>\n")
                    .append("        </gCamIVA>\n")
                    .append("      </gCamItem>\n");
            idx++;
        }

        BigDecimal totalOpe = subExe.add(sub5).add(sub10);
        BigDecimal totalIva = totIva5.add(totIva10);
        BigDecimal totalBaseGrav = baseGrav5.add(baseGrav10);

        String timbradoStr = CdcUtil.leftPadDigits(req.getTimbrado() != null ? req.getTimbrado() : "12345678", 8);
        String fecIniTimb = (req.getFechaInicioTimbrado() != null && !req.getFechaInicioTimbrado().isBlank())
                ? req.getFechaInicioTimbrado().trim().substring(0, Math.min(10, req.getFechaInicioTimbrado().trim().length()))
                : "2025-01-01";
        String nomEmi = (req.getRazonSocialEmisor() != null && !req.getRazonSocialEmisor().isBlank())
                ? req.getRazonSocialEmisor().trim()
                : "CONTRIBUYENTE EMISOR";
        String nomFan = (req.getNombreFantasia() != null && !req.getNombreFantasia().isBlank())
                ? req.getNombreFantasia().trim()
                : nomEmi;

        boolean esCredito = req.getCondicion() != null && req.getCondicion().trim().toUpperCase().startsWith("CRE");

        StringBuilder xml = new StringBuilder();
        xml.append("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        xml.append("<rDE xmlns=\"http://ekuatia.set.gov.py/sifen/xsd\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://ekuatia.set.gov.py/sifen/xsd siRecepDE_v150.xsd\">\n");
        xml.append("  <dVerFor>150</dVerFor>\n");
        xml.append("  <DE Id=\"").append(cdc).append("\">\n");
        xml.append("    <dDVId>").append(dvId).append("</dDVId>\n");
        xml.append("    <dFecFirma>").append(fechaEmisionIso).append("</dFecFirma>\n");
        xml.append("    <dSisFact>1</dSisFact>\n");
        xml.append("    <gOpeDE>\n");
        xml.append("      <iTipEmi>").append(tipEmi).append("</iTipEmi>\n");
        xml.append("      <dDesTipEmi>").append(tipEmi == 2 ? "Contingencia" : "Normal").append("</dDesTipEmi>\n");
        xml.append("      <dCodSeg>").append(codSeg).append("</dCodSeg>\n");
        xml.append("    </gOpeDE>\n");
        xml.append("    <gTimb>\n");
        xml.append("      <iTiDE>").append(tipoDoc).append("</iTiDE>\n");
        xml.append("      <dDesTiDE>").append(descTipoDoc).append("</dDesTiDE>\n");
        xml.append("      <dNumTim>").append(timbradoStr).append("</dNumTim>\n");
        xml.append("      <dEst>").append(est).append("</dEst>\n");
        xml.append("      <dPunExp>").append(punExp).append("</dPunExp>\n");
        xml.append("      <dNumDoc>").append(numDoc).append("</dNumDoc>\n");
        if (req.getSerie() != null && !req.getSerie().isBlank()) {
            xml.append("      <dSerieNum>").append(esc(req.getSerie().trim().toUpperCase())).append("</dSerieNum>\n");
        }
        xml.append("      <dFeIniT>").append(fecIniTimb).append("</dFeIniT>\n");
        xml.append("    </gTimb>\n");
        xml.append("    <gDatGralOpe>\n");
        xml.append("      <dFeEmiDE>").append(fechaEmisionIso).append("</dFeEmiDE>\n");
        xml.append("      <gOpeCom>\n");
        xml.append("        <iTipTra>").append(req.getTipoTransaccion() != null ? req.getTipoTransaccion() : 1).append("</iTipTra>\n");
        xml.append("        <dDesTipTra>Venta de mercadería</dDesTipTra>\n");
        xml.append("        <iTImp>1</iTImp>\n");
        xml.append("        <dDesTImp>IVA</dDesTImp>\n");
        xml.append("        <cMoneOpe>PYG</cMoneOpe>\n");
        xml.append("        <dDesMoneOpe>Guarani</dDesMoneOpe>\n");
        xml.append("      </gOpeCom>\n");
        xml.append("      <gEmis>\n");
        xml.append("        <dRucEm>").append(rucEmisorBase).append("</dRucEm>\n");
        xml.append("        <dDVEmi>").append(dvEmisor).append("</dDVEmi>\n");
        xml.append("        <iTipCont>").append(tipCont).append("</iTipCont>\n");
        xml.append("        <dNomEmi>").append(esc(nomEmi)).append("</dNomEmi>\n");
        xml.append("        <dNomFanEmi>").append(esc(nomFan)).append("</dNomFanEmi>\n");
        xml.append("        <dDirEmi>").append(esc(req.getDireccionEmisor() != null ? req.getDireccionEmisor() : "ASUNCION")).append("</dDirEmi>\n");
        xml.append("        <dNumCas>").append(esc(req.getNumeroCasaEmisor() != null ? req.getNumeroCasaEmisor() : "0")).append("</dNumCas>\n");
        xml.append("        <cDepEmi>").append(req.getCodigoDepartamentoEmisor() != null ? req.getCodigoDepartamentoEmisor() : 1).append("</cDepEmi>\n");
        xml.append("        <dDesDepEmi>").append(esc(req.getDescripcionDepartamentoEmisor() != null ? req.getDescripcionDepartamentoEmisor() : "CAPITAL")).append("</dDesDepEmi>\n");
        xml.append("        <cDisEmi>").append(req.getCodigoDistritoEmisor() != null ? req.getCodigoDistritoEmisor() : 1).append("</cDisEmi>\n");
        xml.append("        <dDesDisEmi>").append(esc(req.getDescripcionDistritoEmisor() != null ? req.getDescripcionDistritoEmisor() : "ASUNCION")).append("</dDesDisEmi>\n");
        xml.append("        <cCiuEmi>").append(req.getCodigoCiudadEmisor() != null ? req.getCodigoCiudadEmisor() : 1).append("</cCiuEmi>\n");
        xml.append("        <dDesCiuEmi>").append(esc(req.getDescripcionCiudadEmisor() != null ? req.getDescripcionCiudadEmisor() : "ASUNCION (DISTRITO)")).append("</dDesCiuEmi>\n");
        xml.append("        <dTelEmi>").append(esc(req.getTelefonoEmisor() != null ? req.getTelefonoEmisor() : "021000000")).append("</dTelEmi>\n");
        xml.append("        <dEmailE>").append(esc(req.getEmailEmisor() != null ? req.getEmailEmisor() : "facturacion@contapro.com.py")).append("</dEmailE>\n");
        xml.append("        <gActEco>\n");
        xml.append("          <cActEco>").append(esc(req.getActividadEconomicaCodigo() != null ? req.getActividadEconomicaCodigo() : "69200")).append("</cActEco>\n");
        xml.append("          <dDesActEco>").append(esc(req.getActividadEconomicaDescripcion() != null ? req.getActividadEconomicaDescripcion() : "ACTIVIDADES DE CONTABILIDAD")).append("</dDesActEco>\n");
        xml.append("        </gActEco>\n");
        xml.append("      </gEmis>\n");
        xml.append("      <gDatRec>\n");
        if (esContribuyente) {
            xml.append("        <iNatRec>1</iNatRec>\n");
            xml.append("        <iTiOpe>").append(rucCliBase.startsWith("80") ? "1" : "2").append("</iTiOpe>\n");
            xml.append("        <cPaisRec>PRY</cPaisRec>\n");
            xml.append("        <dDesPaisRe>Paraguay</dDesPaisRe>\n");
            xml.append("        <iTiContRec>").append(rucCliBase.startsWith("80") ? "2" : "1").append("</iTiContRec>\n");
            xml.append("        <dRucRec>").append(rucCliBase).append("</dRucRec>\n");
            xml.append("        <dDVRec>").append(dvCli).append("</dDVRec>\n");
        } else {
            xml.append("        <iNatRec>2</iNatRec>\n");
            xml.append("        <iTiOpe>2</iTiOpe>\n");
            xml.append("        <cPaisRec>PRY</cPaisRec>\n");
            xml.append("        <dDesPaisRe>Paraguay</dDesPaisRe>\n");
            xml.append("        <iTipIDRec>1</iTipIDRec>\n");
            xml.append("        <dDTipIDRec>Cédula paraguaya</dDTipIDRec>\n");
            xml.append("        <dNumIDRec>").append(rucCliBase).append("</dNumIDRec>\n");
        }
        xml.append("        <dNomRec>").append(esc(nomCliente)).append("</dNomRec>\n");
        xml.append("      </gDatRec>\n");
        xml.append("    </gDatGralOpe>\n");
        xml.append("    <gDtipDE>\n");
        if (tipoDoc == 5 || tipoDoc == 6) {
            xml.append("      <gCamNCDE>\n");
            xml.append("        <iMotEmi>1</iMotEmi>\n");
            xml.append("        <dDesMotEmi>").append(esc(req.getMotivoEmisionNota() != null ? req.getMotivoEmisionNota() : "Devolución y Ajuste de precios")).append("</dDesMotEmi>\n");
            xml.append("      </gCamNCDE>\n");
        } else {
            xml.append("      <gCamFE>\n");
            xml.append("        <iIndPres>1</iIndPres>\n");
            xml.append("        <dDesIndPres>Operación presencial</dDesIndPres>\n");
            xml.append("      </gCamFE>\n");
        }
        xml.append("      <gCamCond>\n");
        if (esCredito) {
            xml.append("        <iCondOpe>2</iCondOpe>\n");
            xml.append("        <dDCondOpe>Crédito</dDCondOpe>\n");
            xml.append("        <gPagCred>\n");
            xml.append("          <iCondCred>1</iCondCred>\n");
            xml.append("          <dDCondCred>Plazo</dDCondCred>\n");
            xml.append("          <dPlazoCre>").append(esc(req.getPlazoCredito() != null ? req.getPlazoCredito() : "30 días")).append("</dPlazoCre>\n");
            xml.append("        </gPagCred>\n");
        } else {
            xml.append("        <iCondOpe>1</iCondOpe>\n");
            xml.append("        <dDCondOpe>Contado</dDCondOpe>\n");
            xml.append("        <gPaConEIni>\n");
            xml.append("          <iTiPago>1</iTiPago>\n");
            xml.append("          <dDesTiPag>Efectivo</dDesTiPag>\n");
            xml.append("          <dMonTiPag>").append(totalOpe.toPlainString()).append("</dMonTiPag>\n");
            xml.append("          <cMoneTiPag>PYG</cMoneTiPag>\n");
            xml.append("          <dDMoneTiPag>Guarani</dDMoneTiPag>\n");
            xml.append("        </gPaConEIni>\n");
        }
        xml.append("      </gCamCond>\n");
        xml.append(itemsXml);
        xml.append("    </gDtipDE>\n");
        xml.append("    <gTotSub>\n");
        xml.append("      <dSubExe>").append(subExe.toPlainString()).append("</dSubExe>\n");
        xml.append("      <dSubExo>0</dSubExo>\n");
        xml.append("      <dSub5>").append(sub5.toPlainString()).append("</dSub5>\n");
        xml.append("      <dSub10>").append(sub10.toPlainString()).append("</dSub10>\n");
        xml.append("      <dTotOpe>").append(totalOpe.toPlainString()).append("</dTotOpe>\n");
        xml.append("      <dTotDesc>0</dTotDesc>\n");
        xml.append("      <dTotDescGlotem>0</dTotDescGlotem>\n");
        xml.append("      <dTotAntItem>0</dTotAntItem>\n");
        xml.append("      <dTotAnt>0</dTotAnt>\n");
        xml.append("      <dPorcDescTotal>0</dPorcDescTotal>\n");
        xml.append("      <dDescTotal>0</dDescTotal>\n");
        xml.append("      <dAnticipo>0</dAnticipo>\n");
        xml.append("      <dRedon>0</dRedon>\n");
        xml.append("      <dTotGralOpe>").append(totalOpe.toPlainString()).append("</dTotGralOpe>\n");
        xml.append("      <dIVA5>").append(totIva5.toPlainString()).append("</dIVA5>\n");
        xml.append("      <dIVA10>").append(totIva10.toPlainString()).append("</dIVA10>\n");
        xml.append("      <dLiqTotIVA5>0</dLiqTotIVA5>\n");
        xml.append("      <dLiqTotIVA10>0</dLiqTotIVA10>\n");
        xml.append("      <dTotIVA>").append(totalIva.toPlainString()).append("</dTotIVA>\n");
        xml.append("      <dBaseGrav5>").append(baseGrav5.toPlainString()).append("</dBaseGrav5>\n");
        xml.append("      <dBaseGrav10>").append(baseGrav10.toPlainString()).append("</dBaseGrav10>\n");
        xml.append("      <dTBasGraIVA>").append(totalBaseGrav.toPlainString()).append("</dTBasGraIVA>\n");
        xml.append("    </gTotSub>\n");
        if ((tipoDoc == 5 || tipoDoc == 6) && req.getCdcDocumentoAsociado() != null && !req.getCdcDocumentoAsociado().isBlank()) {
            xml.append("    <gCamDEAsoc>\n");
            xml.append("      <iTipDocAso>1</iTipDocAso>\n");
            xml.append("      <dDesTipDocAso>Electrónico</dDesTipDocAso>\n");
            xml.append("      <dCdCDERef>").append(esc(req.getCdcDocumentoAsociado().replaceAll("\\D", ""))).append("</dCdCDERef>\n");
            xml.append("    </gCamDEAsoc>\n");
        }
        xml.append("  </DE>\n");
        xml.append("</rDE>");

        return new BuiltDe(
                cdc, codSeg, fechaEmisionIso,
                rucCliBase, esContribuyente, rucCliBase,
                items.size(), subExe, sub5, sub10, totIva5, totIva10, totalIva, totalOpe,
                xml.toString()
        );
    }

    /**
     * Construye el XML de Evento de Cancelación (iTiEvento=1) o Inutilización (iTiEvento=2) para SIFEN v150.
     */
    public static String construirEventoXml(EventoSifenRequest req, int tipoEvento) {
        String nowIso = LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ss"));
        String evtId = "1";
        StringBuilder sb = new StringBuilder();
        sb.append("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        sb.append("<gGroupGesEve xmlns=\"http://ekuatia.set.gov.py/sifen/xsd\">\n");
        sb.append("  <rGesEve>\n");
        sb.append("    <rEve Id=\"").append(evtId).append("\">\n");
        sb.append("      <dFecFirma>").append(nowIso).append("</dFecFirma>\n");
        sb.append("      <dVerFor>150</dVerFor>\n");
        if (tipoEvento == 1) {
            sb.append("      <gGroupTiEvt>\n");
            sb.append("        <rGeVeCan>\n");
            sb.append("          <Id>").append(esc(req.getCdc() != null ? req.getCdc().replaceAll("\\D", "") : "")).append("</Id>\n");
            sb.append("          <mOtEve>").append(esc(req.getMotivo() != null ? req.getMotivo() : "Cancelación de comprobante electrónico")).append("</mOtEve>\n");
            sb.append("        </rGeVeCan>\n");
            sb.append("      </gGroupTiEvt>\n");
        } else {
            sb.append("      <gGroupTiEvt>\n");
            sb.append("        <rGeVeInu>\n");
            sb.append("          <dNumTim>").append(CdcUtil.leftPadDigits(req.getTimbrado(), 8)).append("</dNumTim>\n");
            sb.append("          <dEst>").append(CdcUtil.leftPadDigits(req.getEstablecimiento(), 3)).append("</dEst>\n");
            sb.append("          <dPunExp>").append(CdcUtil.leftPadDigits(req.getPuntoExpedicion(), 3)).append("</dPunExp>\n");
            sb.append("          <dNumIn>").append(CdcUtil.leftPadDigits(req.getNumeroInicio(), 7)).append("</dNumIn>\n");
            sb.append("          <dNumFin>").append(CdcUtil.leftPadDigits(req.getNumeroFin(), 7)).append("</dNumFin>\n");
            sb.append("          <iTiDE>").append(req.getTipoDocumento() != null ? req.getTipoDocumento() : 1).append("</iTiDE>\n");
            sb.append("          <mOtEve>").append(esc(req.getMotivo() != null ? req.getMotivo() : "Inutilización de rango de numeración")).append("</mOtEve>\n");
            sb.append("        </rGeVeInu>\n");
            sb.append("      </gGroupTiEvt>\n");
        }
        sb.append("    </rEve>\n");
        sb.append("  </rGesEve>\n");
        sb.append("</gGroupGesEve>");
        return sb.toString();
    }

    private static String esc(String val) {
        if (val == null) return "";
        return val.replace("&", "&amp;")
                  .replace("<", "&lt;")
                  .replace(">", "&gt;")
                  .replace("\"", "&quot;")
                  .replace("'", "&apos;");
    }
}
