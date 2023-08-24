import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { useParams } from "react-router-dom";
import { API_GET, callSniperPHPAPI } from "../../apis/call";
import FixedLoader from "../../components/FixedLoader";
import { Tab, Tabs, TabList, TabPanel } from "react-tabs";
import "react-tabs/style/react-tabs.css";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
} from "chart.js";
import { Bar, Pie, Line } from "react-chartjs-2";
import { apiCall } from "../../lib/common";
import { connect } from "react-redux";
import { show500Popup, show403Popup, show400Popup } from "../../actions";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
);

const percentage_options = {
  plugins: {
    tooltip: {
      callbacks: {
        title: (tooltipItem) => {
          const currentValue = tooltipItem[0].dataset.data[tooltipItem[0].dataIndex];
          let total = 0;
          tooltipItem[0].dataset.data.forEach(v => total += v)
          const percentage = parseFloat(
            ((currentValue / total) * 100).toFixed(1)
          );
          return currentValue + " (" + percentage + "%)";
        },
      },
    },
  },
  // tooltips: {
  //   callbacks: {
  //     label: (tooltipItem, data) => {
  //       const dataset = data.datasets[tooltipItem.datasetIndex];
  //       const meta = dataset._meta[Object.keys(dataset._meta)[0]];
  //       const total = meta.total;
  //       const currentValue = tooltipItem?.value;
  //       const percentage = parseFloat(
  //         ((currentValue / total) * 100).toFixed(1)
  //       );
  //       return currentValue + " (" + percentage + "%)";
  //     },
  //     title: (tooltipItem) => `${tooltipItem[0]?.label}`,
  //   },
  // },
};

const getDeliveryStatsBarData = (
  delivered = 0,
  not_sent = 0,
  unable_to_deliver = 0
) => {
  return {
    labels: ["Delivered", "Not Sent", "Unable To Deliver"],
    datasets: [
      {
        label: "Delivered",
        data: [delivered],
        stack: 1,
        backgroundColor: "#bfdd00",
      },
      {
        label: "Not Sent",
        data: [not_sent],
        stack: 1,
        backgroundColor: "#def54c",
      },
      {
        label: "Unable To Deliver",
        data: [unable_to_deliver],
        stack: 1,
        backgroundColor: "#ed323d",
      },
    ],
  };
};

const getDeliveryRatePieData = (
  delivered = 0,
  not_sent = 0,
  unable_to_deliver = 0
) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: ["Delivered", "Not Sent", "Unable to Deliver"],
    datasets: [
      {
        data: [delivered, not_sent, unable_to_deliver],
        backgroundColor: ["#bfdd00", "#022a1e", "#c2dedb"],
        hoverBackgroundColor: ["#bfdd00", "#022a1e", "#c2dedb"],
      },
    ],
  };
};

const getOptoutRatePieData = (opted_in = 0, opted_out = 0) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: ["Opted In", "Opted Out"],
    datasets: [
      {
        data: [opted_in, opted_out],
        backgroundColor: ["#bfdd00", "#022a1e"],
        hoverBackgroundColor: ["#bfdd00", "#022a1e"],
      },
    ],
  };
};

const getClicks24hrsLineData = (labels = [], data = []) => {
  return {
    labels: labels,
    datasets: [
      {
        label: "Clicks",
        data: data,
        borderColor: "#bfdd00",
        backgroundColor: "#bfdd00",
      },
    ],
  };
};

const getDailyClicksBarData = (labels = [], data = []) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: labels,
    datasets: [
      {
        data: data,
        backgroundColor: labels.map((v) => "#bfdd00"),
        hoverBackgroundColor: labels.map((v) => "#bfdd00"),
      },
    ],
  };
};

const getClickThroughRateRatePieData = (
  unique_clicks = 0,
  not_clicked = 0
) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: ["Unique Clicks", "Not Clicked"],
    datasets: [
      {
        data: [unique_clicks, not_clicked],
        backgroundColor: ["#bfdd00", "#022a1e"],
        hoverBackgroundColor: ["#bfdd00", "#022a1e"],
      },
    ],
  };
};

const getPassDownloadsBarData = (labels = [], data = []) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: labels,
    datasets: [
      {
        data: data,
        backgroundColor: labels.map((v) => "#bfdd00"),
        hoverBackgroundColor: labels.map((v) => "#bfdd00"),
      },
    ],
  };
};

const getDownloadRatePieData = (downloaded = 0, not_downloaded = 0) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: ["Downloaded", "Not Downloaded"],
    datasets: [
      {
        data: [downloaded, not_downloaded],
        backgroundColor: ["#bfdd00", "#c2dedb"],
        hoverBackgroundColor: ["#bfdd00", "#c2dedb"],
      },
    ],
  };
};

const getDeviceTypePieData = (apple = 0, other = 0) => {
  return {
    maintainAspectRatio: false,
    responsive: false,
    labels: ["Apple", "Other"],
    datasets: [
      {
        data: [apple, other],
        backgroundColor: ["#bfdd00", "#c2dedb"],
        hoverBackgroundColor: ["#bfdd00", "#c2dedb"],
      },
    ],
  };
};

const bar_graph_options = {
  barPercentage: 0.6,
  categoryPercentage: 0.5,
  plugins: {
    title: {
      display: false,
      text: "Chart.js Bar Chart - Stacked",
    },
    legend: {
      display: false,
    },
  },
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      stacked: false,
    },
    y: {
      stacked: false,
    },
  },
  legend: {
    display: false,
  },
};

const line_graph_options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
    },
    title: {
      display: false,
      text: "Customers",
    },
  },
};

const SHORTURL_STATUS_GENERATED = "generated";
const SHORTURL_STATUS_PROCESSING = "processing";
const SHORTURL_STATUS_FAILED = "failed";
const usePollShortUrl = (type, id) => {
  const [shorturl, setShorturl] = useState(null);
  const [pollingAttempts, setPollingAttempts] = useState(0);

  useEffect(() => {
    let pollingInterval;

    const pollData = async () => {
      const { res_success } = await apiCall(() => getCampaignStats(type, id));
      const temp_shorturl = res_success?.data?.data?.shorturl;

      if (temp_shorturl) {
        setShorturl(temp_shorturl);
        if (temp_shorturl.status === SHORTURL_STATUS_GENERATED) {
          clearInterval(pollingInterval);
        }
      }
    };

    pollingInterval = setInterval(() => {
      if (pollingAttempts >= 10) {
        clearInterval(pollingInterval);
      } else {
        pollData();
        setPollingAttempts(pollingAttempts + 1);
      }
    }, 5000);

    return () => clearInterval(pollingInterval);
  }, [type, id]);

  return { shorturl, pollingAttempts };
};

const getCampaignStats = async (type, id) => {
  let url = null;
  switch (type) {
    case "sms":
      url = `campaigns/sms/${id}/stats`;
      break;
    case "mms":
    case "tap2buy":
    case "add2wallet":
      url = `campaigns/mms/${id}/stats`;
      break;
  }

  return await callSniperPHPAPI(API_GET, url);
};

const CampaignsStatsTypeIdPage = ({
  show500Popup,
  show403Popup,
  show400Popup,
}) => {
  let navigate = useNavigate();

  const { id, type } = useParams();

  const [campaign_stats, setCampaignStats] = useState(null);
  const [delivery_stats, setDeliveryStats] = useState(null);
  const [shorturl_stats, setShortURLStats] = useState(null);
  const [add2wallet_stats, setAdd2WalletStats] = useState(null);


  const [delivery_stats_bar_data, setDeliveryStatsBarData] = useState(
    getDeliveryStatsBarData()
  );

  const [delivery_rate_pie_data, setDeliveryRatePieData] = useState(
    getDeliveryRatePieData()
  );

  const [optout_rate_pie_data, setOptoutRatePieData] = useState(
    getOptoutRatePieData()
  );

  const [clicks_24hrs_line_data, setClicks24hrsLineData] = useState(
    getClicks24hrsLineData()
    
  );

  const [daily_clicks_bar_data, setDailyClicksBarData] = useState(
    getDailyClicksBarData()
  );

  const [click_through_rate_pie_data, setClickThroughRatePieData] = useState(
    getClickThroughRateRatePieData()
  );

  const [pass_downloads_bar_data, setPassDownloadsBarData] = useState(
    getPassDownloadsBarData()
  );

  const [download_rate_pie_data, setDownloadRatePieData] = useState(
    getDownloadRatePieData()
  );

  const [devide_type_pie_data, setDeviceTypePieData] = useState(
    getDeviceTypePieData()
  );

  const [show_loader, setShowLoader] = useState(false);

  let heading_text = null;
  let back_url = '/campaigns/summary';

  switch (type) {
    case "sms":
      heading_text = "SMS Campaign Stats";
      back_url = "/campaigns/smssummary";
      break;
    case "mms":
      heading_text = "MMS Campaign Stats";
      back_url = "/campaigns/summary";
      break;
    case "tap2buy":
      heading_text = "Tap2Buy Campaign Stats";
      back_url = "/campaigns/summary/TAP2BUY";
      break;
    case "add2wallet":
      heading_text = "Add2Wallet Campaign Stats";
      back_url = "/campaigns/summary/ADD_2_WALLET";
      break;
  }


  const { shorturl, pollingAttempts } = usePollShortUrl(type, id);

  const renderShorturlContent = () => {
    if (shorturl && shorturl.status === SHORTURL_STATUS_PROCESSING) {
      return <div>Loading...</div>;
    } else if (shorturl && shorturl_stats && shorturl.status === SHORTURL_STATUS_GENERATED) {
      return (
        <>
        <div className="2xl:grid 2xl:grid-cols-2">
          <div className="flex justify-center mb-28">
            <div
              style={{
                maxWidth: 600,
                height: 200,
                width: 800,
              }}
            >
              <div className="text-center text-2xl mb-8">
                Delivery Statistics
              </div>
              <Line
                data={clicks_24hrs_line_data}
                options={line_graph_options}
              />
            </div>
          </div>
          <div className="p-8">
            <div className="text-center text-2xl mb-4">
              Overall Data
            </div>
            <div className="flex justify-center">
              <table
                className="table-auto"
                style={{ minWidth: 300 }}
              >
                <tr>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    Total Links
                  </td>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    {delivery_stats.delivered}
                    {/* 105000 */}
                  </td>
                </tr>
                <tr className="bg-smoke">
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    Total Clicks
                  </td>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    {shorturl_stats.clicks_total}
                    {/* 22948 */}
                  </td>
                </tr>
                <tr>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    Total Unique Clicks
                  </td>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    {shorturl_stats.clicks_unique}
                    {/* 17903 */}
                  </td>
                </tr>
                <tr className="bg-smoke">
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    Click-Through Rate (CTR)
                  </td>
                  <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                    {click_through_rate}%
                    {/* {((22948 / 105000) * 100).toFixed(1)}% */}
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      <div className="2xl:grid 2xl:grid-cols-2">
        <div className="flex justify-center mb-28">
          <div
            style={{
              maxWidth: 600,
              height: 200,
              width: 800,
            }}
          >
            <div className="text-center text-2xl mb-8">
              Daily Clicks
            </div>
            <Bar
              data={daily_clicks_bar_data}
              options={bar_graph_options}
            />
          </div>
        </div>
        <div className="flex justify-center mb-28">
          <div>
            <div className="flex justify-center">
              <div>
                <div className="text-center text-2xl mb-1">
                  Click Through Rate
                </div>
                <div className="text-center text-xl mb-1">{click_through_rate}%</div>
                <div style={{ maxWidth: 250 }}>
                  <Pie
                    data={click_through_rate_pie_data}
                    options={percentage_options}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      </>
      );
    } else if (shorturl && shorturl.status === SHORTURL_STATUS_FAILED) {
      return (
        <div>
          Shorturl Stats not loading. Contact support if this persists.
        </div>
      );
    } else if (shorturl && pollingAttempts >= 10) {
      return (
        <div>
          Short Url Stats taking some time to generate. Check back shortly.
        </div>
      );
    }
  };

  useEffect(async () => {
    setShowLoader(true);

    const { res_success, res_500, res_400, res_403 } = await apiCall(
      async () => {
        return await getCampaignStats(type, id);
      }
    );

    setShowLoader(false);

    if (res_success) {
      const temp_stats = res_success.data.data;

      const campaign = temp_stats.campaign;
      const delivery = temp_stats.delivery;
      const shorturl = temp_stats.shorturl;

      setCampaignStats(campaign);
      setDeliveryStats(delivery);

      // delivery stats
      const delivered = delivery.delivered;
      const not_sent = delivery.not_sent;
      const unable_to_deliver = delivery.unable_to_deliver;
      const opted_in = delivery.opted_in;
      const optouts = delivery.optouts;
      setDeliveryStatsBarData(
        getDeliveryStatsBarData(delivered, not_sent, unable_to_deliver)
      );
      setDeliveryRatePieData(
        getDeliveryRatePieData(delivered, not_sent, unable_to_deliver)
      );
      setOptoutRatePieData(getOptoutRatePieData(opted_in, optouts));

      // shorturl
      if (shorturl.status === "generated") {
        // const temp_shorturl_stats = JSON.parse(
        //   '{"days": {"Mar22": 7, "Mar23": 0, "Mar24": 0, "Mar25": 0, "Mar26": 0, "Mar27": 0, "Mar28": 0}, "last_24h": {"12AM": 0, "01AM": 0, "02AM": 0, "03AM": 0, "04AM": 0, "05AM": 0, "06AM": 0, "07AM": 0, "08AM": 0, "09AM": 3, "10AM": 3, "11AM": 1, "12PM": 0, "01PM": 0, "02PM": 0, "03PM": 0, "04PM": 0, "05PM": 0, "06PM": 0, "07PM": 0, "08PM": 0, "09PM": 0, "10PM": 0, "11PM": 0}, "clicks_total": 7, "clicks_unique": 4}'
        // );

        const temp_shorturl_stats = JSON.parse(shorturl.data);

        console.log("temp_shorturl_stats", temp_shorturl_stats);

        setShortURLStats(temp_shorturl_stats);

        const daily_clicks_labels = [];
        const daily_clicks_data = [];
        Object.keys(temp_shorturl_stats.days).forEach((day) => {
          const value = temp_shorturl_stats.days[day];

          daily_clicks_labels.push(day);
          daily_clicks_data.push(value);
        });
        setDailyClicksBarData(
          getDailyClicksBarData(daily_clicks_labels, daily_clicks_data)
        );

        if(temp_shorturl_stats.clicks_unique === 0 && delivered === 0)
          setClickThroughRatePieData(
            getClickThroughRateRatePieData(100, 0)
          )
        else
          setClickThroughRatePieData(
            getClickThroughRateRatePieData(temp_shorturl_stats.clicks_unique, delivered-temp_shorturl_stats.clicks_unique)
          )

        const clicks_24hrs_labels = [];
        const clicks_24hrs_data = [];
        Object.keys(temp_shorturl_stats.last_24h).forEach((hr) => {
          const value = temp_shorturl_stats.last_24h[hr];

          clicks_24hrs_labels.push(hr);
          clicks_24hrs_data.push(value);
        });
        setClicks24hrsLineData(
          getClicks24hrsLineData(clicks_24hrs_labels, clicks_24hrs_data)
        );
      }

      // add2wallet
      const add2wallet = temp_stats.add2wallet;

      if (add2wallet) {
        setAdd2WalletStats(add2wallet);

        setPassDownloadsBarData(
          getPassDownloadsBarData(
            [
              "Delivered",
              "Total Downloaded",
              "Unique Downloaded",
              "Total Active",
              "Unique Active",
            ],
            [
              delivery.delivered,
              add2wallet.total_downloaded,
              add2wallet.unique_downloaded,
              add2wallet.total_active,
              add2wallet.unique_active,
            ]
          )
        );

        // setPassDownloadsBarData(
        //   getPassDownloadsBarData(
        //     [
        //       "Delivered",
        //       "Total Downloaded",
        //       "Unique Downloaded",
        //       "Total Active",
        //       "Unique Active",
        //     ],
        //     [
        //       105000,
        //       30804,
        //       21839,
        //       4874,
        //       2874,
        //     ]
        //   )
        // );

        setDownloadRatePieData(
          getDownloadRatePieData(
            delivery.delivered - add2wallet.unique_downloaded,
            add2wallet.unique_downloaded
          )
        );

        // setDownloadRatePieData(
        //   getDownloadRatePieData(
        //     21839,
        //     105000 - 21839
        //   )
        // );

        // setDeviceTypePieData(
        //   getDeviceTypePieData(
        //     16839,
        //     13965
        //   )
        // );
      }
    } else if (res_500) {
      show500Popup();
    } else if (res_403) {
      show403Popup();
    } else if (res_400) {
      show400Popup(res_400.messages);
    }
  }, []);

  let click_through_rate = 0;
  console.log('shorturl_stats', shorturl_stats)
  if (campaign_stats && delivery_stats && shorturl_stats) {
    if(shorturl_stats.clicks_unique === 0 && delivery_stats.delivered === 0)
      click_through_rate = 100;
    else {
      click_through_rate =
        (shorturl_stats.clicks_unique / delivery_stats.delivered) * 100;
      click_through_rate = click_through_rate.toFixed(1);
    }
  }

  return (
    <div className="min-height-page w-full sm:p-4 bg-white">
      <div className="px-12 md:px-8">
        <div className="xl:mt-8 xl:mb-12 mt-0 mb-4 relative">
          <div className="font-bold text-center text-2xl mb-6">
            {heading_text}
          </div>
          <div
            className="lg:flex hidden justify-end w-full absolute"
            style={{ top: 0, right: 0 }}
          >
            <div className="flex sm:mb-0 mb-4">
              <button
                className="bg-alt-lime text-sacramento py-2 px-5 rounded-md"
                onClick={() => {
                  navigate(back_url);
                }}
              >
                Back to Campaigns
              </button>
            </div>
          </div>
        </div>
        <div>
          {campaign_stats && delivery_stats && (
            <div>
              <div>
                <div className="lg:grid lg:grid-cols-3">
                  <div>
                    <div className="px-8 py-4">
                      <div className="text-center text-xl mb-4">
                        Campaign Details
                      </div>
                      <div className="flex justify-center">
                        <table className="table-auto w-full">
                          <tr className="bg-sea-glass">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Item
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Info
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Campaign ID
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {campaign_stats.entity_id}
                              {/* 5830 */}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Campaign Name
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {type === 'sms' ? campaign_stats.sms_campaign_name : campaign_stats.campaign_name }
                              {/* August Promotion */}
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Campaign Description
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {type === 'sms' ? campaign_stats.sms_campaign_description : campaign_stats.campaign_description}
                              {/* Promotion for new products this month */}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Sent Date
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.scheduled_delivery_date}
                              {/* 2022-10-01 12:00:00 */}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Expiry Date
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.expiry_date}
                            </td>
                          </tr>
                        </table>
                      </div>
                    </div>
                    <div className="px-8 py-4">
                      <div className="text-center text-xl mb-4">
                        Delivery Details
                      </div>
                      <div className="flex justify-center">
                        <table className="table-auto w-full">
                          <tr className="bg-sea-glass">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Item
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Info
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Total Intended Recipients
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.total_recipients}
                              {/* 105000 */}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Delivered
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.delivered}
                              {/* 105000 */}
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Not Sent
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.not_sent}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Subscriber Failed
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.subscriber_failed}
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Bounced
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.bounced}
                            </td>
                          </tr>
                          <tr className="bg-smoke">
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Replies
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.replies}
                              {/* 2094 */}
                            </td>
                          </tr>
                          <tr>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              Optouts
                            </td>
                            <td className="border border-sea-glass p-2.5 text-xs pr-8 text-left">
                              {delivery_stats.optouts}
                              {/* 973 */}
                            </td>
                          </tr>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div className="col-span-2 ml-4">
                    <Tabs>
                      <TabList>
                        <Tab>Delivery Stats</Tab>
                        <Tab>Click Stats</Tab>
                        {add2wallet_stats && <Tab>Add2Wallet Stats</Tab>}
                      </TabList>
                      <TabPanel>
                        <div className="px-4 py-8">
                          <div className="flex justify-center mb-28">
                            <div
                              style={{ maxWidth: 600, height: 150, width: 800 }}
                            >
                              <div className="text-center text-2xl mb-8">
                                Delivery Statistics
                              </div>
                              <Bar
                                data={delivery_rate_pie_data}
                                options={bar_graph_options}
                              />
                            </div>
                          </div>
                          <div className="md:grid md:grid-cols-2">
                            <div className="flex justify-center">
                              <div className="flex justify-center">
                                <div>
                                  <div className="text-center text-2xl mb-8">
                                    Delivery Rate
                                  </div>
                                  <div style={{ maxWidth: 250 }}>
                                    <Pie
                                      data={delivery_rate_pie_data}
                                      options={percentage_options}
                                    />
                                  </div>
                                </div>
                              </div>
                            </div>
                            <div className="flex justify-center">
                              <div>
                                <div className="text-center text-2xl mb-14">
                                  Opt Out Rate
                                </div>
                                <div style={{ maxWidth: 230 }}>
                                  <Pie
                                    data={optout_rate_pie_data}
                                    options={percentage_options}
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </TabPanel>
                      <TabPanel>
                        <div className="px-4 py-8">{renderShorturlContent()}</div>
                      </TabPanel>
                      {add2wallet_stats && (
                        <TabPanel>
                          <div className="px-4 py-8">
                            <div className="flex justify-center mb-28">
                              <div
                                style={{
                                  maxWidth: 600,
                                  height: 200,
                                  width: 800,
                                }}
                              >
                                <div className="text-center text-2xl mb-8">
                                  Delivery Statistics
                                </div>
                                <Bar
                                  data={pass_downloads_bar_data}
                                  options={bar_graph_options}
                                />
                              </div>
                            </div>
                            <div className="md:grid md:grid-cols-2">
                              <div className="flex justify-center">
                                <div className="flex justify-center">
                                  <div style={{ maxWidth: 600 }}>
                                    <div className="text-center text-2xl mb-8">
                                      Download Rate
                                    </div>
                                    <Pie data={download_rate_pie_data} options={percentage_options} />
                                  </div>
                                </div>
                              </div>
                              <div className="flex justify-center">
                                <div style={{ maxWidth: 600 }}>
                                  <div className="text-center text-2xl mb-8">
                                    Device Type Rate
                                  </div>
                                  <Pie data={devide_type_pie_data} options={percentage_options} />
                                </div>
                              </div>
                            </div>
                          </div>
                        </TabPanel>
                      )}
                    </Tabs>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
      {show_loader && <FixedLoader />}
    </div>
  );
};

export default connect(null, { show500Popup, show403Popup, show400Popup })(
  CampaignsStatsTypeIdPage
);
