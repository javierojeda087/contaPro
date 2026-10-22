package com.contapro.sifen.service;

import com.contapro.sifen.dto.EmitirDeRequest;
import com.contapro.sifen.dto.EmitirDeResponse;
import com.contapro.sifen.dto.EventoSifenRequest;
import com.contapro.sifen.util.CdcUtil;
import com.contapro.sifen.util.SifenXmlBuilder;
import com.contapro.sifen.util.SifenXmlSigner;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;

import javax.net.ssl.KeyManagerFactory;
import javax.net.ssl.SSLContext;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import java.security.SecureRandom;
import java.time.Duration;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Servicio central del microservicio SIFEN en Java.
 * Gestiona generación de CDC, armado XML v150, firma digital PKCS#12, QR y transmisión SOAP 1.2 mTLS a la DNIT.
 */
@Service
public class SifenService {

    @Value("${sifen.url.test.recepcion}")
    private String urlTestRecepcion;

    @Value("${sifen.url.test.evento}")
    private String urlTestEvento;

    @Value("${sifen.url.test.consulta}")
    private String urlTestConsulta;

    @Value("${sifen.url.test.qr}")
    private String urlTestQr;

    @Value("${sifen.url.prod.recepcion}")
    private String urlProdRecepcion;

    @Value("${sifen.url.prod.evento}")
    private String urlProdEvento;

    @Value("${sifen.url.prod.consulta}")
    private String urlProdConsulta;

    @Value("${sifen.url.prod.qr}")
    private String urlProdQr;

    public EmitirDeResponse emitirDocumento(EmitirDeRequest req) {
        EmitirDeResponse resp = new EmitirDeResponse();
        try {
            boolean isProd = "PROD".equalsIgnoreCase(req.getAmbiente()) || "PRODUCCION".equalsIgnoreCase(req.getAmbiente());
            String baseQrUrl = isProd ? urlProdQr : urlTestQr;
            String endpointRecepcion = isProd ? urlProdRecepcion : urlTestRecepcion;

            // 1. Construir XML SIFEN v150 y calcular CDC de 44 dígitos
            SifenXmlBuilder.BuiltDe built = SifenXmlBuilder.construirDeXml(req);

            // 2. Firmar con certificado .p12 (si se proporcionó) y generar nodo QR (<gCamFuFD>)
            SifenXmlSigner.SignedResult signed = SifenXmlSigner.firmarDeYGenerarQr(
                    built,
                    req.getCertificadoBase64(),
                    req.getCertificadoPath(),
                    req.getCertificadoPassword(),
                    req.getIdCsc(),
                    req.getCsc(),
                    baseQrUrl
            );

            String est = CdcUtil.leftPadDigits(req.getEstablecimiento() != null ? req.getEstablecimiento() : "001", 3);
            String pun = CdcUtil.leftPadDigits(req.getPuntoExpedicion() != null ? req.getPuntoExpedicion() : "001", 3);
            String num = CdcUtil.leftPadDigits(req.getNumeroDocumento() != null ? req.getNumeroDocumento() : "0000001", 7);

            resp.setStatus("success");
            resp.setCdc(built.cdc);
            resp.setCdcFormateado(CdcUtil.formatearCdcParaKude(built.cdc));
            resp.setQrUrl(signed.qrUrl);
            resp.setNroFactura(est + "-" + pun + "-" + num);
            resp.setXmlFirmado(signed.signedXml);
            resp.setGrav10(built.subGrav10);
            resp.setIva10(built.iva10);
            resp.setGrav5(built.subGrav5);
            resp.setIva5(built.iva5);
            resp.setExentas(built.subExentas);
            resp.setTotalIva(built.totalIva);
            resp.setTotalGeneral(built.totalGeneral);
            resp.setFechaProceso(LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")));

            // 3. Si cuenta con certificado digital PKCS#12 real, transmitir a SIFEN vía SOAP 1.2 + mTLS
            if (signed.firmadoConCertificadoReal) {
                try {
                    String xmlSinDeclaracion = signed.signedXml.replaceFirst("<\\?xml[^>]+\\?>", "").trim();
                    String soapEnvelope = """
                            <?xml version="1.0" encoding="UTF-8"?>
                            <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                              <env:Header/>
                              <env:Body>
                                <rEnviDe xmlns="http://ekuatia.set.gov.py/sifen/xsd">
                                  <dId>1</dId>
                                  <xDE>%s</xDE>
                                </rEnviDe>
                              </env:Body>
                            </env:Envelope>
                            """.formatted(xmlSinDeclaracion);

                    String soapResp = enviarSoapMtls(
                            endpointRecepcion,
                            soapEnvelope,
                            req.getCertificadoBase64(),
                            req.getCertificadoPath(),
                            req.getCertificadoPassword()
                    );

                    String codRes = extraerTagXml(soapResp, "dCodRes");
                    String msgRes = extraerTagXml(soapResp, "dMsgRes");
                    String estRes = extraerTagXml(soapResp, "dEstRes");
                    String protAut = extraerTagXml(soapResp, "dProtAut");

                    resp.setCodigoRespuesta(codRes.isEmpty() ? "0260" : codRes);
                    resp.setMensajeRespuesta(msgRes.isEmpty() ? "Documento procesado por SIFEN" : msgRes);
                    resp.setNroProtocolo(protAut);
                    if ("Aprobado".equalsIgnoreCase(estRes) || "0260".equals(codRes) || "1005".equals(codRes)) {
                        resp.setEstadoSifen("APROBADO");
                    } else if (!estRes.isEmpty()) {
                        resp.setEstadoSifen(estRes.toUpperCase());
                    } else {
                        resp.setEstadoSifen("ENVIADO");
                    }
                } catch (Exception soapEx) {
                    resp.setEstadoSifen("FIRMADO_PENDIENTE_ENVIO");
                    resp.setCodigoRespuesta("LOCAL-SIGNED");
                    resp.setMensajeRespuesta("XML firmado y QR generado correctamente. Pendiente de transmisión a SIFEN: " + soapEx.getMessage());
                }
            } else {
                // Modo Homologación / Prueba sin certificado .p12 aún cargado
                resp.setEstadoSifen("HOMOLOGACION_SIMULADO");
                resp.setCodigoRespuesta("0260");
                resp.setNroProtocolo("SIM-" + System.currentTimeMillis());
                resp.setMensajeRespuesta("DE v150, CDC de 44 dígitos y código QR generados con éxito (Modo Prueba sin certificado .p12).");
            }

            return resp;
        } catch (Exception e) {
            resp.setStatus("error");
            resp.setEstadoSifen("ERROR");
            resp.setCodigoRespuesta("-1");
            resp.setMensajeRespuesta("Error al procesar Documento Electrónico: " + e.getMessage());
            return resp;
        }
    }

    public Map<String, Object> procesarEvento(EventoSifenRequest req, int tipoEvento) {
        Map<String, Object> res = new LinkedHashMap<>();
        try {
            boolean isProd = "PROD".equalsIgnoreCase(req.getAmbiente()) || "PRODUCCION".equalsIgnoreCase(req.getAmbiente());
            String endpointEvento = isProd ? urlProdEvento : urlTestEvento;
            String xmlEvento = SifenXmlBuilder.construirEventoXml(req, tipoEvento);

            res.put("status", "success");
            res.put("tipoEvento", tipoEvento == 1 ? "CANCELACION" : "INUTILIZACION");
            res.put("cdc", req.getCdc());
            res.put("xmlEvento", xmlEvento);

            KeyStore ks = SifenXmlSigner.cargarKeyStore(req.getCertificadoBase64(), req.getCertificadoPath(), req.getCertificadoPassword());
            if (ks != null) {
                String soapEnvelope = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                          <env:Header/>
                          <env:Body>
                            <rEnviEventoDe xmlns="http://ekuatia.set.gov.py/sifen/xsd">
                              <dId>1</dId>
                              <dEvReg>%s</dEvReg>
                            </rEnviEventoDe>
                          </env:Body>
                        </env:Envelope>
                        """.formatted(xmlEvento.replaceFirst("<\\?xml[^>]+\\?>", "").trim());

                String soapResp = enviarSoapMtls(
                        endpointEvento,
                        soapEnvelope,
                        req.getCertificadoBase64(),
                        req.getCertificadoPath(),
                        req.getCertificadoPassword()
                );
                res.put("codigoRespuesta", extraerTagXml(soapResp, "dCodRes"));
                res.put("mensajeRespuesta", extraerTagXml(soapResp, "dMsgRes"));
                res.put("estadoEvento", extraerTagXml(soapResp, "dEstRes"));
            } else {
                res.put("codigoRespuesta", "0600");
                res.put("estadoEvento", "SIMULADO");
                res.put("mensajeRespuesta", (tipoEvento == 1 ? "Evento de Cancelación" : "Evento de Inutilización")
                        + " registrado correctamente (Modo Homologación).");
            }
        } catch (Exception e) {
            res.put("status", "error");
            res.put("message", "Error al procesar evento SIFEN: " + e.getMessage());
        }
        return res;
    }

    public Map<String, Object> consultarCdc(String cdcRaw, String ambiente, String certBase64, String certPath, String certPassword) {
        Map<String, Object> info = CdcUtil.decodificarCdc(cdcRaw);
        if (!Boolean.TRUE.equals(info.get("valido"))) {
            info.put("status", "error");
            return info;
        }
        info.put("status", "success");

        boolean isProd = "PROD".equalsIgnoreCase(ambiente) || "PRODUCCION".equalsIgnoreCase(ambiente);
        String endpointConsulta = isProd ? urlProdConsulta : urlTestConsulta;

        try {
            KeyStore ks = SifenXmlSigner.cargarKeyStore(certBase64, certPath, certPassword);
            if (ks != null) {
                String soapEnvelope = """
                        <?xml version="1.0" encoding="UTF-8"?>
                        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                          <env:Header/>
                          <env:Body>
                            <rEnviConsDeRequest xmlns="http://ekuatia.set.gov.py/sifen/xsd">
                              <dId>1</dId>
                              <dCDC>%s</dCDC>
                            </rEnviConsDeRequest>
                          </env:Body>
                        </env:Envelope>
                        """.formatted(info.get("cdc"));

                String soapResp = enviarSoapMtls(endpointConsulta, soapEnvelope, certBase64, certPath, certPassword);
                String codRes = extraerTagXml(soapResp, "dCodRes");
                String msgRes = extraerTagXml(soapResp, "dMsgRes");
                String contenDe = extraerTagXml(soapResp, "xContenDE");

                info.put("codigoRespuestaSifen", codRes);
                info.put("mensajeSifen", msgRes);
                if (!contenDe.isEmpty()) {
                    info.put("razonSocialEmisor", extraerTagXml(contenDe, "dNomEmi"));
                    info.put("nroTimbrado", extraerTagXml(contenDe, "dNumTim"));
                    info.put("totalOperacion", extraerTagXml(contenDe, "dTotGralOpe"));
                    info.put("totalIva", extraerTagXml(contenDe, "dTotIVA"));
                    info.put("grav10", extraerTagXml(contenDe, "dSub10"));
                    info.put("grav5", extraerTagXml(contenDe, "dSub5"));
                    info.put("exentas", extraerTagXml(contenDe, "dSubExe"));
                }
            } else {
                info.put("estadoSifen", "VALIDADO_ESTRUCTURA_MOD11");
            }
        } catch (Exception ex) {
            info.put("avisoConsultaRemota", ex.getMessage());
        }
        return info;
    }

    private String enviarSoapMtls(String url, String soapXml, String certBase64, String certPath, String certPassword) throws Exception {
        KeyStore ks = SifenXmlSigner.cargarKeyStore(certBase64, certPath, certPassword);
        if (ks == null) {
            throw new IllegalArgumentException("No se proporcionó certificado digital PKCS#12 para conexión mTLS con SIFEN.");
        }
        char[] passChars = certPassword != null ? certPassword.toCharArray() : new char[0];
        KeyManagerFactory kmf = KeyManagerFactory.getInstance(KeyManagerFactory.getDefaultAlgorithm());
        kmf.init(ks, passChars);

        SSLContext sslContext = SSLContext.getInstance("TLSv1.2");
        sslContext.init(kmf.getKeyManagers(), null, new SecureRandom());

        HttpClient client = HttpClient.newBuilder()
                .sslContext(sslContext)
                .connectTimeout(Duration.ofSeconds(15))
                .build();

        String cleanEndpoint = url.endsWith("?wsdl") ? url.substring(0, url.length() - 5) : url;

        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(cleanEndpoint))
                .timeout(Duration.ofSeconds(30))
                .header("Content-Type", "application/soap+xml; charset=utf-8")
                .POST(HttpRequest.BodyPublishers.ofString(soapXml, StandardCharsets.UTF_8))
                .build();

        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString(StandardCharsets.UTF_8));
        return response.body();
    }

    private String extraerTagXml(String xml, String tag) {
        if (xml == null || xml.isEmpty()) return "";
        String open = "<" + tag + ">";
        String close = "</" + tag + ">";
        int s = xml.indexOf(open);
        if (s == -1) {
            // Intentar con prefijo de namespace ej: <ns2:dCodRes>
            int idx = xml.indexOf(":" + tag + ">");
            if (idx == -1) return "";
            s = idx + tag.length() + 2;
            int e = xml.indexOf(":" + tag + ">", s);
            if (e == -1) return "";
            int closeStart = xml.lastIndexOf("</", e);
            return closeStart > s ? xml.substring(s, closeStart).trim() : "";
        }
        s += open.length();
        int e = xml.indexOf(close, s);
        return e > s ? xml.substring(s, e).trim() : "";
    }
}
